<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_payments', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('company_id');
            }
        });

        Schema::table('supplier_ledger_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_ledger_entries', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('company_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });

        Schema::table('supplier_ledger_entries', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });
    }
};
