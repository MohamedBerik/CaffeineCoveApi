<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('patient_id'); // (Customer model حاليا)

            // V1: doctor as text (V2: doctors table)
            $table->string('doctor_name')->nullable();

            $table->date('appointment_date');
            $table->time('appointment_time');

            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])
                ->default('scheduled');

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'appointment_date']);
            $table->index(['company_id', 'patient_id']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
