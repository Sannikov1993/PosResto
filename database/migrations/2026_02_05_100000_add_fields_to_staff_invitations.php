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
        Schema::table('staff_invitations', function (Blueprint $table) {
            // Link to existing user (for password reset, existing employee invitations)
            if (!Schema::hasColumn('staff_invitations', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('restaurant_id')
                    ->constrained('users')->nullOnDelete();
            }

            // Tenant ID for multi-tenant support
            if (!Schema::hasColumn('staff_invitations', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('restaurant_id')
                    ->constrained('tenants')->nullOnDelete();
            }

            // Type of invitation
            if (!Schema::hasColumn('staff_invitations', 'type')) {
                $table->string('type', 30)->default('invitation')->after('token')
                    ->comment('invitation, password_reset');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('staff_invitations', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('staff_invitations', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
            if (Schema::hasColumn('staff_invitations', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
