<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            // Тип товара: simple (обычный), parent (родитель с вариантами), variant (вариант)
            $table->enum('product_type', ['simple', 'parent', 'variant'])->default('simple')->after('id');

            // Ссылка на родительский товар (для вариантов)
            $table->foreignId('parent_id')->nullable()->after('product_type')
                ->constrained('dishes')->onDelete('cascade');

            // Название варианта (например "25 см", "4 шт", "300мл")
            $table->string('variant_name', 50)->nullable()->after('name');

            // Внешний ID для интеграций (Яндекс.Еда, сайт и т.д.)
            $table->string('api_external_id', 100)->nullable()->after('sku');

            // Порядок сортировки вариантов
            $table->unsignedInteger('variant_sort')->default(0)->after('sort_order');

            // Индексы
            $table->index(['product_type', 'is_available']);
            $table->index('parent_id');
            $table->index('api_external_id');
        });
    }

    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['product_type', 'is_available']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['api_external_id']);

            $table->dropColumn([
                'product_type',
                'parent_id',
                'variant_name',
                'api_external_id',
                'variant_sort'
            ]);
        });
    }
};
