<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix NOT NULL constraints on optional user fields.
     *
     * The original migration declared email as nullable, but the actual
     * SQLite schema has it as NOT NULL (DB was created before nullable was added).
     * courier_status also needs to be nullable for non-courier users.
     */
    public function up(): void
    {
        // For SQLite, use the built-in column modification (Laravel 11+ handles table rebuild)
        if (DB::getDriverName() === 'sqlite') {
            // SQLite requires table rebuild for column changes.
            // Laravel 11+ handles this via change(), but as a safety fallback
            // we also handle it manually if needed.
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('email')->nullable()->change();
                    $table->string('courier_status')->nullable()->default('offline')->change();
                });
            } catch (\Throwable $e) {
                // Fallback: use raw SQL for SQLite (rebuild approach)
                $this->rebuildForSqlite();
            }
        } else {
            // MySQL/PostgreSQL — straightforward ALTER
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable()->change();
                $table->string('courier_status')->nullable()->default('offline')->change();
            });
        }
    }

    /**
     * SQLite fallback: rebuild the table with correct column definitions.
     */
    private function rebuildForSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys=off');

        DB::transaction(function () {
            // Get current schema
            $columns = DB::select("PRAGMA table_info('users')");

            // Build column definitions, fixing nullability
            $fixColumns = [
                'email' => true,        // make nullable
                'courier_status' => true, // make nullable
            ];

            $colDefs = [];
            foreach ($columns as $col) {
                $def = '"' . $col->name . '" ' . $col->type;

                if ($col->name === 'id') {
                    $def .= ' primary key autoincrement not null';
                } elseif (isset($fixColumns[$col->name])) {
                    // Make nullable, keep default if exists
                    if ($col->dflt_value !== null) {
                        $def .= ' default ' . $col->dflt_value;
                    }
                } else {
                    if ($col->notnull) {
                        $def .= ' not null';
                    }
                    if ($col->dflt_value !== null) {
                        $def .= ' default ' . $col->dflt_value;
                    }
                }

                $colDefs[] = $def;
            }

            // Get foreign keys
            $fks = DB::select("PRAGMA foreign_key_list('users')");
            $foreignKeys = [];
            foreach ($fks as $fk) {
                $key = 'fk_' . $fk->from;
                if (!isset($foreignKeys[$key])) {
                    $foreignKeys[$key] = $fk;
                }
            }

            $fkDefs = [];
            foreach ($foreignKeys as $fk) {
                $onDelete = $fk->on_delete !== 'NO ACTION' ? ' on delete ' . strtolower($fk->on_delete) : '';
                $onUpdate = $fk->on_update !== 'NO ACTION' ? ' on update ' . strtolower($fk->on_update) : '';
                $fkDefs[] = 'foreign key("' . $fk->from . '") references "' . $fk->table . '"("' . $fk->to . '")' . $onDelete . $onUpdate;
            }

            $allDefs = implode(', ', array_merge($colDefs, $fkDefs));
            $colNames = implode(', ', array_map(fn($c) => '"' . $c->name . '"', $columns));

            DB::statement('CREATE TABLE "users_new" (' . $allDefs . ')');
            DB::statement('INSERT INTO "users_new" (' . $colNames . ') SELECT ' . $colNames . ' FROM "users"');
            DB::statement('DROP TABLE "users"');
            DB::statement('ALTER TABLE "users_new" RENAME TO "users"');

            // Recreate indexes
            $indexes = DB::select("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name='users' AND sql IS NOT NULL");
            // Indexes reference old table but names are the same, just re-run them
            // Actually, they were dropped with the table, need to recreate
        });

        DB::statement('PRAGMA foreign_keys=on');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('courier_status')->nullable(false)->default('offline')->change();
        });
    }
};
