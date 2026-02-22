<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixCompanyIdOnOrderItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */

    public function up()
    {
        // 1) عالج أي بيانات قديمة company_id = null
        DB::statement("
        UPDATE order_items oi
        JOIN orders o ON o.id = oi.order_id
        SET oi.company_id = o.company_id
        WHERE oi.company_id IS NULL
    ");

        // 2) اجعل العمود not nullable (لو DB تسمح)
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
