<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) add column nullable أولاً
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id');
            }
        });

        // 2) backfill company_id from orders
        DB::statement("
            UPDATE order_items oi
            JOIN orders o ON o.id = oi.order_id
            SET oi.company_id = o.company_id
            WHERE oi.company_id IS NULL
        ");

        // 3) make it NOT NULL + FK (لو جدول الشركات موجود)
        Schema::table('order_items', function (Blueprint $table) {
            // خليها NOT NULL
            DB::statement("ALTER TABLE order_items MODIFY company_id BIGINT UNSIGNED NOT NULL");

            // FK
            $table->foreign('company_id')
                ->references('id')->on('companies')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropIndex(['company_id']);
                $table->dropColumn('company_id');
            }
        });
    }
};
