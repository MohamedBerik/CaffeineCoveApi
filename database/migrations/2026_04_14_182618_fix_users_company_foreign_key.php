<?php
// database/migrations/xxxx_xx_xx_xxxxxx_fix_users_company_foreign_key.php

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
        $defaultCompany = Company::firstOrCreate(
            ['slug' => 'default-clinic'],
            [
                'name' => 'Default Clinic',
                'status' => 'active', // ✅ استخدم القيمة النصية مباشرة
            ]
        );

        // ✅ 2. تحديث كل المستخدمين اللي company_id = null
        User::whereNull('company_id')->update([
            'company_id' => $defaultCompany->id
        ]);

        // ✅ 3. إزالة nullable من company_id
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
        });

        // ✅ 4. إضافة Foreign Key Constraint
        Schema::table('users', function (Blueprint $table) {
            // نتأكد إن مفيش foreign key قديم
            $this->dropForeignIfExists('users', 'users_company_id_foreign');

            // إضافة الـ foreign key الجديد
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $this->dropForeignIfExists('users', 'users_company_id_foreign');
            $table->unsignedBigInteger('company_id')->nullable()->change();
        });
    }

    private function dropForeignIfExists($table, $foreignKey)
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($foreignKey) {
                $table->dropForeign($foreignKey);
            });
        } catch (\Exception $e) {
            // Foreign key doesn't exist
        }
    }
};
