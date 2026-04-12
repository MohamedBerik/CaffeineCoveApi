<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('patient_radiologies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('dental_record_id')->nullable();
            $table->string('title');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->default('xray');
            $table->string('tooth_number')->nullable();
            $table->date('captured_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('dental_record_id')->references('id')->on('dental_records')->onDelete('set null');

            $table->index(['company_id', 'customer_id']);
            $table->index(['customer_id', 'captured_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('patient_radiologies');
    }
};
