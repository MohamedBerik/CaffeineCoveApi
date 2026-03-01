<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_unique_slot_to_appointments.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // اسم index مهم عشان تقدر تعمل drop بسهولة
            $table->unique(
                ['company_id', 'doctor_id', 'appointment_date', 'appointment_time'],
                'appointments_unique_slot'
            );
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_unique_slot');
        });
    }
};
