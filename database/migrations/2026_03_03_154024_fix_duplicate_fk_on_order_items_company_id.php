<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('order_items')) {
            return;
        }

        // Drop the manually-added duplicate FK if it exists
        $fks = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'order_items'
              AND COLUMN_NAME = 'company_id'
              AND REFERENCED_TABLE_NAME = 'companies'
        ");

        $names = array_map(fn($r) => $r->CONSTRAINT_NAME, $fks);

        if (in_array('fk_order_items_company_id', $names, true)) {
            DB::statement("ALTER TABLE order_items DROP FOREIGN KEY fk_order_items_company_id");
        }

        // Ensure the Laravel conventional FK exists; if it doesn't, add it
        // (Optional safety — usually it exists already عندك)
        $namesAfter = array_map(fn($r) => $r->CONSTRAINT_NAME, DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'order_items'
              AND COLUMN_NAME = 'company_id'
              AND REFERENCED_TABLE_NAME = 'companies'
        "));

        if (!in_array('order_items_company_id_foreign', $namesAfter, true)) {
            DB::statement("
                ALTER TABLE order_items
                ADD CONSTRAINT order_items_company_id_foreign
                FOREIGN KEY (company_id) REFERENCES companies(id)
                ON DELETE CASCADE
            ");
        }
    }

    public function down(): void
    {
        // No-op (we don't want to reintroduce duplicates)
    }
};
