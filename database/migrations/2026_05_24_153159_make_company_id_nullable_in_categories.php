<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeCompanyIdNullableInCategories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            // إذا كان branch_id موجودًا أيضًا:
            if (Schema::hasColumn('categories', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->change();
            }
        });
    }

    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            if (Schema::hasColumn('categories', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable(false)->change();
            }
        });
    }
}
