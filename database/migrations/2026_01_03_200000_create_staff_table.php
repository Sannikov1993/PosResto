<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff')) {
            Schema::create('staff', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->default(1);
                $table->foreignId('user_id')->nullable();
                $table->string('name');
                $table->string('phone', 20)->nullable();
                $table->string('email', 100)->nullable();
                $table->string('position', 50)->nullable();
                $table->string('role', 30)->default('staff');
                $table->string('pin', 10)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['restaurant_id', 'is_active']);
            });

            // Создаём системного сотрудника для POS
            \DB::table('staff')->insert([
                'id' => 1,
                'restaurant_id' => 1,
                'name' => 'Кассир POS',
                'position' => 'cashier',
                'role' => 'cashier',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
