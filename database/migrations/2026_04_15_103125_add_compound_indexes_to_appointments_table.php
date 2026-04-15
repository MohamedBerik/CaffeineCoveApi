<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_compound_indexes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // ✅ Compound index للمواعيد
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['company_id', 'appointment_date'], 'idx_company_appointment_date');
            $table->index(['company_id', 'patient_id'], 'idx_company_patient');
        });

        // ✅ Indexes إضافية للأداء
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['company_id', 'status'], 'idx_company_status');
        });
    }

    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_company_appointment_date');
            $table->dropIndex('idx_company_patient');
            $table->dropIndex('idx_company_status');
        });
    }
};
