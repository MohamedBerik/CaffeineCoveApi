<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['company_id', 'reminder_status']);
            $table->index(['company_id', 'updated_at']);
            $table->index(['company_id', 'reminder_retry_count']);
        });
    }

    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'reminder_status']);
            $table->dropIndex(['company_id', 'updated_at']);
            $table->dropIndex(['company_id', 'reminder_retry_count']);
        });
    }
};
