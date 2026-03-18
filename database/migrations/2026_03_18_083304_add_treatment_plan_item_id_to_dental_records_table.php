<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTreatmentPlanItemIdToDentalRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dental_records', function (Blueprint $table) {
            $table->unsignedBigInteger('treatment_plan_item_id')->nullable()->after('procedure_id');

            $table->foreign('treatment_plan_item_id')
                ->references('id')
                ->on('treatment_plan_items')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dental_records', function (Blueprint $table) {
            //
        });
    }
}
