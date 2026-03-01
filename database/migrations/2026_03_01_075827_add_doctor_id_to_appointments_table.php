

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'doctor_id')) {
                $table->unsignedBigInteger('doctor_id')->nullable()->after('patient_id');
                $table->index(['company_id', 'doctor_id', 'appointment_date', 'appointment_time'], 'appts_slot_idx');
            }
        });

        // لو عايز تنقل بيانات doctor_name القديمة ل doctor_id:
        // 1) تعمل seed/create doctors من أسماء قديمة
        // 2) update appointments doctor_id accordingly
        // (هنعمله بعد ما نضيف Controller)
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'doctor_id')) {
                $table->dropIndex('appts_slot_idx');
                $table->dropColumn('doctor_id');
            }
        });
    }
};
