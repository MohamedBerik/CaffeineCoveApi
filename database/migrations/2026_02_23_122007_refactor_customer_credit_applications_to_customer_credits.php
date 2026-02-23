<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) rename table
        if (Schema::hasTable('customer_credit_applications') && !Schema::hasTable('customer_credits')) {
            Schema::rename('customer_credit_applications', 'customer_credits');
        }

        Schema::table('customer_credits', function (Blueprint $table) {
            // 2) columns adjustments
            if (!Schema::hasColumn('customer_credits', 'payment_id')) {
                $table->unsignedBigInteger('payment_id')->nullable()->after('invoice_id');
                $table->index(['company_id', 'payment_id']);
            }

            if (!Schema::hasColumn('customer_credits', 'type')) {
                $table->enum('type', ['credit', 'debit'])->default('debit')->after('payment_id');
            }

            if (!Schema::hasColumn('customer_credits', 'entry_date')) {
                $table->date('entry_date')->nullable()->after('amount');
                $table->index(['company_id', 'entry_date']);
            }

            if (!Schema::hasColumn('customer_credits', 'description')) {
                $table->string('description')->nullable()->after('entry_date');
            }

            // invoice_id لازم يبقى nullable
            // هنعدلها بـ SQL مباشر لتفادي DBAL issues
        });

        // 3) make invoice_id nullable (raw SQL)
        // ملاحظة: ده يفترض invoice_id BIGINT UNSIGNED
        DB::statement("ALTER TABLE `customer_credits` MODIFY `invoice_id` BIGINT UNSIGNED NULL");

        // 4) migrate applied_at -> entry_date (لو كان موجود)
        if (Schema::hasColumn('customer_credits', 'applied_at')) {
            DB::statement("UPDATE `customer_credits` SET `entry_date` = DATE(`applied_at`) WHERE `entry_date` IS NULL AND `applied_at` IS NOT NULL");
        }

        // 5) set type for existing rows (كل القديم كان 'debit' لأنه تطبيق رصيد على فاتورة)
        DB::statement("UPDATE `customer_credits` SET `type` = 'debit' WHERE `type` IS NULL");

        // 6) drop applied_at بعد التحويل
        if (Schema::hasColumn('customer_credits', 'applied_at')) {
            Schema::table('customer_credits', function (Blueprint $table) {
                $table->dropColumn('applied_at');
            });
        }
    }

    public function down(): void
    {
        // rollback صعب هنا لأننا عملنا refactor فعلي
        // الأفضل تسيبه أو تعمل reverse بحذر لو محتاج
    }
};
