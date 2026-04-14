<?php
// database/migrations/xxxx_xx_xx_xxxxxx_fix_users_company_nullable.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Company;

return new class extends Migration
{
    public function up()
    {
        // ✅ 1. إنشاء Company افتراضي للمستخدمين اللي معندهمش company_id
        $company = DB::table('companies')->where('slug', 'default-clinic')->first();

        if (!$company) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => 'Default Clinic',
                'slug' => 'default-clinic',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $companyId = $company->id;
        }

        // ✅ 2. تحديث كل المستخدمين اللي company_id = null
        DB::table('users')
            ->whereNull('company_id')
            ->update(['company_id' => $companyId]);

        // ✅ 3. إزالة nullable من company_id
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
        });
    }
};
