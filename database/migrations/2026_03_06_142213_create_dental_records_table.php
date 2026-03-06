<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dental_records', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('procedure_id')->nullable();

            $table->string('tooth_number', 10);
            $table->string('surface', 50)->nullable();

            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])
                ->default('planned');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'appointment_id']);
            $table->index(['company_id', 'procedure_id']);
            $table->index(['company_id', 'tooth_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_records');
    }
};
