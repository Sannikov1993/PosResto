<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

class FixCustomerNames extends Command
{
    protected $signature = 'customers:fix-names {--dry-run : Показать изменения без сохранения}';
    protected $description = 'Исправить регистр имён клиентов (Первая буква заглавная)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 Режим просмотра (без сохранения)');
        }

        $customers = Customer::whereNotNull('name')->get();
        $fixed = 0;
        $skipped = 0;

        $this->info("Найдено клиентов: {$customers->count()}");
        $this->newLine();

        foreach ($customers as $customer) {
            $oldName = $customer->name;
            $newName = $this->formatName($oldName);

            if ($oldName !== $newName) {
                $this->line("  #{$customer->id}: <fg=red>{$oldName}</> → <fg=green>{$newName}</>");

                if (!$dryRun) {
                    $customer->name = $newName;
                    $customer->save();
                }
                $fixed++;
            } else {
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("✅ Исправлено: {$fixed}");
        $this->info("⏭️  Пропущено (уже корректно): {$skipped}");

        if ($dryRun && $fixed > 0) {
            $this->newLine();
            $this->warn('Для применения изменений запустите без --dry-run:');
            $this->line('  php artisan customers:fix-names');
        }

        return 0;
    }

    /**
     * Форматирование имени: первая буква каждого слова заглавная
     */
    private function formatName(string $name): string
    {
        // Убираем лишние пробелы
        $name = trim(preg_replace('/\s+/', ' ', $name));

        // Разбиваем на слова
        $words = explode(' ', $name);

        // Форматируем каждое слово
        $formatted = array_map(function ($word) {
            if (empty($word)) return '';

            // Для кириллицы используем mb_* функции
            $first = mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
            $rest = mb_strtolower(mb_substr($word, 1, null, 'UTF-8'), 'UTF-8');

            return $first . $rest;
        }, $words);

        return implode(' ', array_filter($formatted));
    }
}
