<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up()
    {
        // 1. تحديث القيم الفارغة
        Company::whereNull('slug')->orWhere('slug', '')->each(function ($company) {
            $company->slug = Str::slug($company->name) . '-' . uniqid();
            $company->save();
        });

        // 2. جعل slug NOT NULL
        Schema::table('companies', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });

        // 3. تغيير status لـ enum
        DB::statement("ALTER TABLE companies MODIFY COLUMN status ENUM('trial', 'active', 'suspended', 'cancelled') DEFAULT 'trial'");

        // 4. إضافة indexes مع تجاهل الأخطاء
        $this->addIndexSafely('companies', 'status');
        $this->addIndexSafely('companies', 'created_at');
        $this->addIndexSafely('companies', ['status', 'created_at']);
    }

    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('slug')->nullable()->change();
        });

        // إزالة indexes
        $this->dropIndexSafely('companies', ['status', 'created_at']);
        $this->dropIndexSafely('companies', 'created_at');
        $this->dropIndexSafely('companies', 'status');

        // إرجاع status لـ string
        DB::statement("ALTER TABLE companies MODIFY COLUMN status VARCHAR(255) DEFAULT 'trial'");
    }

    private function addIndexSafely($table, $columns)
    {
        $indexName = is_array($columns)
            ? $table . '_' . implode('_', $columns) . '_index'
            : $table . '_' . $columns . '_index';

        // التحقق من وجود الـ index
        $indexExists = $this->checkIndexExists($table, $indexName);

        if (!$indexExists) {
            Schema::table($table, function (Blueprint $table) use ($columns) {
                $table->index($columns);
            });
        }
    }

    private function dropIndexSafely($table, $columns)
    {
        $indexName = is_array($columns)
            ? $table . '_' . implode('_', $columns) . '_index'
            : $table . '_' . $columns . '_index';

        // التحقق من وجود الـ index
        $indexExists = $this->checkIndexExists($table, $indexName);

        if ($indexExists) {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }

    private function checkIndexExists($table, $indexName)
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};
