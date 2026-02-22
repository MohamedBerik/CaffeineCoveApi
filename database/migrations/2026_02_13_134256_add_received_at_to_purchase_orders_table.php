<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReceivedAtToPurchaseOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('purchase_orders', 'received_at')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->timestamp('received_at')->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('purchase_orders', 'received_at')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('received_at');
            });
        }
    }
}
