<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite не поддерживает изменение foreign keys, поэтому пересоздаём таблицу

            // 1. Сохраняем данные
            $data = DB::table('cash_operations')->get();

            // 2. Отключаем foreign keys
            DB::statement('PRAGMA foreign_keys = OFF');

            // 3. Удаляем старую таблицу
            Schema::dropIfExists('cash_operations');

            // 4. Создаём новую таблицу с правильным FK (user_id -> users вместо staff)
            Schema::create('cash_operations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('cash_shift_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('type', ['income', 'expense', 'deposit', 'withdrawal', 'correction']);
                $table->string('category')->nullable();
                $table->decimal('amount', 10, 2);
                $table->enum('payment_method', ['cash', 'card', 'transfer', 'other'])->default('cash');
                $table->string('description')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('fiscal_receipt_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();

                $table->index(['restaurant_id', 'created_at']);
                $table->index(['cash_shift_id', 'type']);
            });

            // 5. Восстанавливаем данные (с отключенными FK)
            foreach ($data as $row) {
                DB::table('cash_operations')->insert([
                    'id' => $row->id,
                    'restaurant_id' => $row->restaurant_id,
                    'cash_shift_id' => $row->cash_shift_id,
                    'order_id' => $row->order_id,
                    'user_id' => $row->user_id,
                    'type' => $row->type,
                    'category' => $row->category,
                    'amount' => $row->amount,
                    'payment_method' => $row->payment_method,
                    'description' => $row->description,
                    'notes' => $row->notes,
                    'fiscal_receipt_id' => $row->fiscal_receipt_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            // 6. Включаем foreign keys обратно
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            // MySQL/PostgreSQL - просто пропускаем, FK уже правильный
            // или можно добавить ALTER TABLE если нужно
        }
    }

    public function down(): void
    {
        // Откат не требуется - структура правильная
    }
};
