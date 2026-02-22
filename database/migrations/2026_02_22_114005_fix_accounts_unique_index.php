<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {

            // احذف unique القديم على code
            $table->dropUnique('accounts_code_unique');

            // أضف composite unique
            $table->unique(['company_id', 'code'], 'accounts_company_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {

            $table->dropUnique('accounts_company_code_unique');

            $table->unique('code', 'accounts_code_unique');
        });
    }
};
