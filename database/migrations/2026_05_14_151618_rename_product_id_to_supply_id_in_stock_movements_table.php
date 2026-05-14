<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameProductIdToSupplyIdInStockMovementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('stock_movements', 'product_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->renameColumn('product_id', 'supply_id');
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
        if (Schema::hasColumn('stock_movements', 'supply_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->renameColumn('supply_id', 'product_id');
            });
        }
    }
}
