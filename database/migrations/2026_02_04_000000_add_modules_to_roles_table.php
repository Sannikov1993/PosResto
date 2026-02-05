<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add module access fields to roles table
 *
 * This implements Level 2 of the 3-level access control:
 * - Level 1: Interface Access (can_access_pos, can_access_backoffice)
 * - Level 2: Module Access (pos_modules, backoffice_modules) <-- NEW
 * - Level 3: Functional Permissions (orders.view, orders.create, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // POS modules: ['cash', 'orders', 'delivery', 'customers', 'warehouse', 'stoplist', 'writeoffs', 'settings']
            $table->json('pos_modules')->nullable()->after('can_access_delivery')
                ->comment('Allowed POS modules/tabs');

            // Backoffice modules: ['dashboard', 'menu', 'pricelists', 'hall', 'staff', 'attendance', 'inventory', 'customers', 'loyalty', 'delivery', 'finance', 'analytics', 'integrations', 'settings']
            $table->json('backoffice_modules')->nullable()->after('pos_modules')
                ->comment('Allowed Backoffice modules/tabs');
        });

        // Set default modules for existing roles based on their access
        $this->setDefaultModules();
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['pos_modules', 'backoffice_modules']);
        });
    }

    /**
     * Set default module access for existing roles
     */
    private function setDefaultModules(): void
    {
        $allPosModules = ['cash', 'orders', 'delivery', 'customers', 'warehouse', 'stoplist', 'writeoffs', 'settings'];
        $allBackofficeModules = ['dashboard', 'menu', 'pricelists', 'hall', 'staff', 'attendance', 'inventory', 'customers', 'loyalty', 'delivery', 'finance', 'analytics', 'integrations', 'settings'];

        // Owner & Admin - full access
        \DB::table('roles')
            ->whereIn('key', ['owner', 'admin'])
            ->update([
                'pos_modules' => json_encode($allPosModules),
                'backoffice_modules' => json_encode($allBackofficeModules),
            ]);

        // Manager - most modules
        \DB::table('roles')
            ->where('key', 'manager')
            ->update([
                'pos_modules' => json_encode(['cash', 'orders', 'delivery', 'customers', 'warehouse', 'stoplist', 'writeoffs']),
                'backoffice_modules' => json_encode(['dashboard', 'menu', 'hall', 'staff', 'customers', 'loyalty', 'finance', 'analytics']),
            ]);

        // Waiter - limited POS
        \DB::table('roles')
            ->where('key', 'waiter')
            ->update([
                'pos_modules' => json_encode(['cash', 'orders']),
                'backoffice_modules' => json_encode([]),
            ]);

        // Cashier - cash focused
        \DB::table('roles')
            ->where('key', 'cashier')
            ->update([
                'pos_modules' => json_encode(['cash', 'orders', 'customers']),
                'backoffice_modules' => json_encode([]),
            ]);

        // Cook - kitchen only (no POS modules)
        \DB::table('roles')
            ->where('key', 'cook')
            ->update([
                'pos_modules' => json_encode([]),
                'backoffice_modules' => json_encode([]),
            ]);

        // Courier - delivery only
        \DB::table('roles')
            ->where('key', 'courier')
            ->update([
                'pos_modules' => json_encode([]),
                'backoffice_modules' => json_encode([]),
            ]);

        // Hostess - orders and customers
        \DB::table('roles')
            ->where('key', 'hostess')
            ->update([
                'pos_modules' => json_encode(['orders', 'customers']),
                'backoffice_modules' => json_encode([]),
            ]);
    }
};
