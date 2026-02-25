<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('appointment_id')->nullable()->after('order_id');

            // يمنع تكرار فاتورة لنفس الموعد داخل نفس الشركة
            $table->unique(['company_id', 'appointment_id'], 'uniq_company_appointment_invoice');

            $table->foreign('appointment_id')
                ->references('id')->on('appointments')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
            $table->dropUnique('uniq_company_appointment_invoice');
            $table->dropColumn('appointment_id');
        });
    }
};
