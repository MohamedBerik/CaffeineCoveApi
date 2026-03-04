<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('order_items') || !Schema::hasTable('orders')) {
            return;
        }

        if (!Schema::hasColumn('order_items', 'company_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id');
            });
        }

        $hasNulls = DB::table('order_items')->whereNull('company_id')->exists();

        if ($hasNulls && Schema::hasColumn('orders', 'company_id')) {

            if (DB::getDriverName() === 'sqlite') {

                DB::statement("
                    UPDATE order_items
                    SET company_id = (
                        SELECT company_id
                        FROM orders
                        WHERE orders.id = order_items.order_id
                    )
                    WHERE company_id IS NULL
                ");
            } else {

                DB::statement("
                    UPDATE order_items oi
                    JOIN orders o ON o.id = oi.order_id
                    SET oi.company_id = o.company_id
                    WHERE oi.company_id IS NULL
                ");
            }
        }

        if (DB::getDriverName() !== 'sqlite') {

            DB::statement("ALTER TABLE order_items MODIFY company_id BIGINT UNSIGNED NOT NULL");

            $exists = DB::selectOne("
                SELECT COUNT(*) c
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'order_items'
                AND COLUMN_NAME = 'company_id'
                AND REFERENCED_TABLE_NAME = 'companies'
            ");

            if ((int)$exists->c === 0) {
                DB::statement("
                    ALTER TABLE order_items
                    ADD CONSTRAINT order_items_company_id_foreign
                    FOREIGN KEY (company_id)
                    REFERENCES companies(id)
                    ON DELETE CASCADE
                ");
            }
        }
    }

    public function down(): void {}
};
