<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('notes')->nullable();
            $table->integer('bonus_points')->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamps();
            
            $table->index(['restaurant_id', 'phone']);
            $table->index(['restaurant_id', 'total_orders']);
            $table->unique(['restaurant_id', 'phone']);
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('title', 50)->default('Дом'); // Дом, Работа
            $table->string('street');
            $table->string('apartment', 50)->nullable();
            $table->string('entrance', 10)->nullable();
            $table->string('floor', 10)->nullable();
            $table->string('intercom', 20)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
