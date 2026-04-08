<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('appointments', function (Blueprint $table) {
            // ✅ حذف الـ indexes القديمة لو موجودة
            $this->dropIndexIfExists('appointments', 'appointments_company_id_reminder_status_index');
            $this->dropIndexIfExists('appointments', 'appointments_company_id_updated_at_index');
            $this->dropIndexIfExists('appointments', 'appointments_company_id_reminder_retry_count_index');

            // ✅ إنشاء الـ indexes من جديد
            if (Schema::hasColumn('appointments', 'reminder_status')) {
                $table->index(['company_id', 'reminder_status'], 'company_reminder_status_idx');
            }

            if (Schema::hasColumn('appointments', 'updated_at')) {
                $table->index(['company_id', 'updated_at'], 'company_updated_at_idx');
            }

            if (Schema::hasColumn('appointments', 'reminder_retry_count')) {
                $table->index(['company_id', 'reminder_retry_count'], 'company_reminder_retry_idx');
            }
        });
    }

    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $this->dropIndexIfExists('appointments', 'company_reminder_status_idx');
            $this->dropIndexIfExists('appointments', 'company_updated_at_idx');
            $this->dropIndexIfExists('appointments', 'company_reminder_retry_idx');
        });
    }

    /**
     * ✅ دالة مساعدة لحذف index لو موجود
     */
    private function dropIndexIfExists($table, $indexName)
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        } catch (\Exception $e) {
            // الفهرس مش موجود - تجاهل
        }
    }
};
