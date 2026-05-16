<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMaxBranchesToPlansTable extends Migration
{
    // database/migrations/xxxx_add_max_branches_to_plans_table.php
    public function up()
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_branches')->nullable()->after('max_appointments');
        });
    }

    public function down()
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_branches');
        });
    }
}
