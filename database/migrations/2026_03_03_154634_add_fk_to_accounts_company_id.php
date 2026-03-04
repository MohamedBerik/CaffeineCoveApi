<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('accounts') || !Schema::hasTable('companies')) {
            return;
        }

        // If FK already exists, do nothing
        $fks = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'accounts'
              AND COLUMN_NAME = 'company_id'
              AND REFERENCED_TABLE_NAME = 'companies'
        ");

        if (count($fks) > 0) {
            return;
        }

        DB::statement("
            ALTER TABLE accounts
            ADD CONSTRAINT accounts_company_id_foreign
            FOREIGN KEY (company_id) REFERENCES companies(id)
            ON DELETE CASCADE
        ");
    }

    public function down(): void
    {
        // No-op
    }
};
