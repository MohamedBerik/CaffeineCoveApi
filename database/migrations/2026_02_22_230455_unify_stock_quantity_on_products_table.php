<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1️⃣ لو stock_quantity فاضية أو صفر — نملأها من quantity
        DB::statement("
            UPDATE products
            SET stock_quantity = quantity
            WHERE stock_quantity IS NULL
        ");

        // 2️⃣ نخلي stock_quantity NOT NULL default 0
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_quantity')
                ->default(0)
                ->nullable(false)
                ->change();
        });

        // ❌ لا نحذف quantity الآن (مرحلة انتقالية آمنة)
    }

    public function down()
    {
        // rollback بسيط
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_quantity')
                ->nullable()
                ->change();
        });
    }
};
