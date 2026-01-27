<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем лимиты и дополнительные настройки для ролей (как в Saby/iiko)
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Лимиты для операций
            $table->unsignedTinyInteger('max_discount_percent')->default(0)
                ->comment('Максимальный % скидки (0 = нельзя, 100 = любая)');
            $table->unsignedInteger('max_refund_amount')->default(0)
                ->comment('Максимальная сумма возврата (0 = нельзя)');
            $table->unsignedInteger('max_cancel_amount')->default(0)
                ->comment('Максимальная сумма отмены заказа (0 = нельзя)');

            // Доступ к интерфейсам
            $table->boolean('can_access_pos')->default(false)
                ->comment('Доступ к POS терминалу');
            $table->boolean('can_access_backoffice')->default(false)
                ->comment('Доступ к бэк-офису');
            $table->boolean('can_access_kitchen')->default(false)
                ->comment('Доступ к кухонному экрану');
            $table->boolean('can_access_delivery')->default(false)
                ->comment('Доступ к приложению курьера');

            // Дополнительные ограничения
            $table->boolean('require_manager_confirm')->default(false)
                ->comment('Требуется подтверждение менеджера для операций');
            $table->json('allowed_halls')->nullable()
                ->comment('Доступные залы (null = все)');
            $table->json('allowed_payment_methods')->nullable()
                ->comment('Доступные способы оплаты (null = все)');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn([
                'max_discount_percent',
                'max_refund_amount',
                'max_cancel_amount',
                'can_access_pos',
                'can_access_backoffice',
                'can_access_kitchen',
                'can_access_delivery',
                'require_manager_confirm',
                'allowed_halls',
                'allowed_payment_methods',
            ]);
        });
    }
};
