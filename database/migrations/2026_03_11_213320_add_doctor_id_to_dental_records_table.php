<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDoctorIdToDentalRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('dental_records', function (Blueprint $table) {
            $table->unsignedBigInteger('doctor_id')->nullable()->after('appointment_id');

            $table->foreign('doctor_id')
                ->references('id')
                ->on('doctors')
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
