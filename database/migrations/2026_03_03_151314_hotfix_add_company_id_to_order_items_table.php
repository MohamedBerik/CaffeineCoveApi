<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('order_items') || !Schema::hasTable('orders')) {
            return;
        }

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

    public function down(): void {}
};
