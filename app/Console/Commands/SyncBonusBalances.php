<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\BonusTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncBonusBalances extends Command
{
    protected $signature = 'bonus:sync {--dry-run : Показать изменения без применения}';
    protected $description = 'Синхронизировать bonus_balance клиентов из суммы транзакций';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? 'Режим просмотра (без изменений):' : 'Синхронизация балансов...');
        $this->newLine();

        // Получаем суммы транзакций по клиентам
        $transactionSums = BonusTransaction::select('customer_id', DB::raw('SUM(amount) as total'))
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $updated = 0;
        $skipped = 0;
        $differences = [];

        // Проходим по всем клиентам с бонусами
        $customers = Customer::where('bonus_balance', '>', 0)
            ->orWhereIn('id', $transactionSums->keys())
            ->get();

        foreach ($customers as $customer) {
            $currentBalance = (int) $customer->bonus_balance;
            $transactionBalance = (int) ($transactionSums[$customer->id] ?? 0);

            if ($currentBalance !== $transactionBalance) {
                $diff = $transactionBalance - $currentBalance;
                $differences[] = [
                    'id' => $customer->id,
                    'name' => $customer->name ?? $customer->phone,
                    'current' => $currentBalance,
                    'should_be' => $transactionBalance,
                    'diff' => $diff,
                ];

                if (!$dryRun) {
                    $customer->update(['bonus_balance' => $transactionBalance]);
                    $updated++;
                }
            } else {
                $skipped++;
            }
        }

        // Показываем таблицу различий
        if (count($differences) > 0) {
            $this->table(
                ['ID', 'Клиент', 'Было', 'Стало', 'Разница'],
                collect($differences)->map(fn($d) => [
                    $d['id'],
                    mb_substr($d['name'] ?? '-', 0, 20),
                    $d['current'],
                    $d['should_be'],
                    ($d['diff'] >= 0 ? '+' : '') . $d['diff'],
                ])
            );
        }

        $this->newLine();
        if ($dryRun) {
            $this->warn("Найдено расхождений: " . count($differences));
            $this->info("Запустите без --dry-run чтобы применить изменения");
        } else {
            $this->info("Обновлено: {$updated}");
            $this->info("Без изменений: {$skipped}");
        }

        return 0;
    }
}
