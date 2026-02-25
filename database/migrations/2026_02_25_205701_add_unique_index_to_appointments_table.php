<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // لو doctor_name ممكن يكون null: الأفضل نخليه NOT NULL في V1
            // أو نستخدم doctor_key. (هنمشي بالحل الأبسط: نخليه string not null)
            $table->string('doctor_name')->nullable(false)->change();

            $table->unique(
                ['company_id', 'doctor_name', 'appointment_date', 'appointment_time'],
                'uniq_company_doctor_datetime'
            );
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('uniq_company_doctor_datetime');
            $table->string('doctor_name')->nullable()->change();
        });
    }
};
