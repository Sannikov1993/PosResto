<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix stock_movements table - add missing columns and fix nullable
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_movements', 'warehouse_id')) {
                    $table->foreignId('warehouse_id')->nullable()->after('restaurant_id')->constrained();
                }
                if (!Schema::hasColumn('stock_movements', 'document_type')) {
                    $table->string('document_type', 30)->nullable()->after('type');
                }
                if (!Schema::hasColumn('stock_movements', 'document_id')) {
                    $table->unsignedBigInteger('document_id')->nullable()->after('document_type');
                }
                if (!Schema::hasColumn('stock_movements', 'notes')) {
                    $table->text('notes')->nullable()->after('reason');
                }
                if (!Schema::hasColumn('stock_movements', 'movement_date')) {
                    $table->timestamp('movement_date')->nullable()->after('notes');
                }
            });

            // Make quantity_before and quantity_after nullable if they exist
            if (Schema::hasColumn('stock_movements', 'quantity_before')) {
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->decimal('quantity_before', 12, 3)->nullable()->change();
                });
            }
            if (Schema::hasColumn('stock_movements', 'quantity_after')) {
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->decimal('quantity_after', 12, 3)->nullable()->change();
                });
            }
        }

        // Add timestamps to ingredient_categories if missing
        if (Schema::hasTable('ingredient_categories') && !Schema::hasColumn('ingredient_categories', 'created_at')) {
            Schema::table('ingredient_categories', function (Blueprint $table) {
                $table->timestamps();
            });
        }

        // Add timestamps to units if missing
        if (Schema::hasTable('units') && !Schema::hasColumn('units', 'created_at')) {
            Schema::table('units', function (Blueprint $table) {
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                if (Schema::hasColumn('stock_movements', 'warehouse_id')) {
                    $table->dropConstrainedForeignId('warehouse_id');
                }
                if (Schema::hasColumn('stock_movements', 'document_type')) {
                    $table->dropColumn('document_type');
                }
                if (Schema::hasColumn('stock_movements', 'document_id')) {
                    $table->dropColumn('document_id');
                }
                if (Schema::hasColumn('stock_movements', 'notes')) {
                    $table->dropColumn('notes');
                }
                if (Schema::hasColumn('stock_movements', 'movement_date')) {
                    $table->dropColumn('movement_date');
                }
            });
        }

        if (Schema::hasColumn('ingredient_categories', 'created_at')) {
            Schema::table('ingredient_categories', function (Blueprint $table) {
                $table->dropTimestamps();
            });
        }

        if (Schema::hasColumn('units', 'created_at')) {
            Schema::table('units', function (Blueprint $table) {
                $table->dropTimestamps();
            });
        }
    }
};
