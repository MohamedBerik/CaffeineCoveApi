<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customers')) return;

        Schema::table('customers', function (Blueprint $table) {

            // company_id (لو مش موجود)
            if (!Schema::hasColumn('customers', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id');
            }

            // email nullable
            if (Schema::hasColumn('customers', 'email')) {
                $table->string('email')->nullable()->change();
            }

            // password nullable
            if (Schema::hasColumn('customers', 'password')) {
                $table->string('password')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // اختياري: غالبًا مانرجعش ده في PROD
    }
};
