<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // optional safety check for duplicates before adding unique index
        $duplicates = DB::table('invoices')
            ->select('company_id', 'number', DB::raw('COUNT(*) as c'))
            ->groupBy('company_id', 'number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->count() > 0) {
            throw new RuntimeException('Cannot add unique index: duplicate invoice numbers exist per company.');
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['company_id', 'number'], 'invoices_company_id_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_company_id_number_unique');
        });
    }
};
