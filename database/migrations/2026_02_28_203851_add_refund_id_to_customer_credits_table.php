<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_credits', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_credits', 'refund_id')) {
                $table->unsignedBigInteger('refund_id')->nullable()->after('payment_id');
            }
        });

        // ✅ Unique when refund_id IS NOT NULL (MySQL)
        // يمنع تكرار نفس refund_id داخل نفس الشركة
        DB::statement("
            CREATE UNIQUE INDEX customer_credits_company_refund_unique
            ON customer_credits (company_id, refund_id)
        ");
    }

    public function down(): void
    {
        // drop index then column
        DB::statement("DROP INDEX customer_credits_company_refund_unique ON customer_credits");

        Schema::table('customer_credits', function (Blueprint $table) {
            if (Schema::hasColumn('customer_credits', 'refund_id')) {
                $table->dropColumn('refund_id');
            }
        });
    }
};
