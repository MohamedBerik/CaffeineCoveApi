<?php
// database/migrations/xxxx_xx_xx_xxxxxx_fix_appointments_foreign_keys.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('appointments', function (Blueprint $table) {
            // ✅ 1. تنظيف البيانات الفاسدة قبل إضافة foreign keys
            DB::statement("
                DELETE FROM appointments
                WHERE company_id NOT IN (SELECT id FROM companies)
                   OR patient_id NOT IN (SELECT id FROM customers)
            ");

            // ✅ 2. إضافة Foreign Key لـ company_id
            $this->dropForeignIfExists('appointments', 'appointments_company_id_foreign');
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');

            // ✅ 3. إضافة Foreign Key لـ patient_id (يرجع لجدول customers)
            $this->dropForeignIfExists('appointments', 'appointments_patient_id_foreign');
            $table->foreign('patient_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');

            // ✅ 4. إضافة Foreign Key لـ created_by (اختياري)
            if (Schema::hasColumn('appointments', 'created_by')) {
                $this->dropForeignIfExists('appointments', 'appointments_created_by_foreign');
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $this->dropForeignIfExists('appointments', 'appointments_created_by_foreign');
            $this->dropForeignIfExists('appointments', 'appointments_patient_id_foreign'); // ✅ تم التصحيح
            $this->dropForeignIfExists('appointments', 'appointments_company_id_foreign');
        });
    }

    private function dropForeignIfExists($table, $foreignKey)
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($foreignKey) {
                $table->dropForeign($foreignKey);
            });
        } catch (\Exception $e) {
            // Foreign key doesn't exist
        }
    }
};
