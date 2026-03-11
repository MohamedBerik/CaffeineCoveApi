<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('price');
            $table->unsignedBigInteger('appointment_id')->nullable()->after('status');
            $table->timestamp('started_at')->nullable()->after('appointment_id');
            $table->timestamp('completed_at')->nullable()->after('started_at');

            $table->index(['company_id', 'treatment_plan_id', 'status'], 'tpi_company_plan_status_idx');
            $table->index(['appointment_id'], 'tpi_appointment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropIndex('tpi_company_plan_status_idx');
            $table->dropIndex('tpi_appointment_idx');

            $table->dropColumn([
                'status',
                'appointment_id',
                'started_at',
                'completed_at',
            ]);
        });
    }
};
