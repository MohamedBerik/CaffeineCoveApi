<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('clinic_settings', 'next_invoice_number')) {
                $table->unsignedBigInteger('next_invoice_number')->default(1)->after('invoice_start_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            if (Schema::hasColumn('clinic_settings', 'next_invoice_number')) {
                $table->dropColumn('next_invoice_number');
            }
        });
    }
};
