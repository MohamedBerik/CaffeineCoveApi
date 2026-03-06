<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClinicSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('clinic_settings', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('company_id')->unique();

            $table->string('clinic_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('currency', 10)->default('USD');
            $table->string('timezone')->default('UTC');

            $table->string('invoice_prefix')->default('INV');
            $table->integer('invoice_start_number')->default(1);

            $table->string('language')->default('en');

            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('clinic_settings');
    }
}
