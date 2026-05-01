<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBranchIdToCoreTables extends Migration
{
    public function up()
    {
        $tables = ['appointments', 'customers', 'invoices', 'payments', 'orders', 'sales'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
                });
            }
        }
    }

    public function down()
    {
        $tables = ['appointments', 'customers', 'invoices', 'payments', 'orders', 'sales'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                });
            }
        }
    }
}
