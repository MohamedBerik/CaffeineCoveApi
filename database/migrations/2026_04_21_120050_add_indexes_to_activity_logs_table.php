<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // ✅ إضافة الإندكسات لتحسين الأداء
            $table->index(['company_id', 'created_at'], 'idx_activity_logs_company_created');
            $table->index(['action', 'created_at'], 'idx_activity_logs_action_created');
            $table->index(['subject_type', 'subject_id'], 'idx_activity_logs_subject');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // ✅ حذف الإندكسات عند التراجع
            $table->dropIndex('idx_activity_logs_company_created');
            $table->dropIndex('idx_activity_logs_action_created');
            $table->dropIndex('idx_activity_logs_subject');
        });
    }
};
