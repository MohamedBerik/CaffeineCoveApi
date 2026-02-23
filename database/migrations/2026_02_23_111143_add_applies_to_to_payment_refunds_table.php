<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$indexName]);
        return !empty($rows);
    }

    public function up(): void
    {
        // 1) لو العمود مش موجود: ضيفه ENUM مباشرة
        if (!Schema::hasColumn('payment_refunds', 'applies_to')) {
            DB::statement("ALTER TABLE `payment_refunds`
                ADD `applies_to` ENUM('invoice','credit') NOT NULL DEFAULT 'invoice'
                AFTER `payment_id`");
        } else {
            // 2) العمود موجود: نظّف القيم ثم حوّله ENUM (لو كان varchar)
            DB::statement("UPDATE `payment_refunds`
                SET `applies_to` = 'invoice'
                WHERE `applies_to` IS NULL
                   OR `applies_to` NOT IN ('invoice','credit')");

            // ⚠️ تحويل النوع (MySQL)
            DB::statement("ALTER TABLE `payment_refunds`
                MODIFY `applies_to` ENUM('invoice','credit') NOT NULL DEFAULT 'invoice'");
        }

        // 3) index اختياري لكن مفيد
        $idx = 'payment_refunds_company_applies_to_idx';
        if (!$this->indexExists('payment_refunds', $idx)) {
            DB::statement("CREATE INDEX `$idx`
                ON `payment_refunds` (`company_id`, `applies_to`)");
        }
    }

    public function down(): void
    {
        // رجّع النوع varchar (Rollback)
        if (Schema::hasColumn('payment_refunds', 'applies_to')) {
            DB::statement("ALTER TABLE `payment_refunds`
                MODIFY `applies_to` varchar(255) NOT NULL DEFAULT 'invoice'");
        }

        $idx = 'payment_refunds_company_applies_to_idx';
        if ($this->indexExists('payment_refunds', $idx)) {
            DB::statement("DROP INDEX `$idx` ON `payment_refunds`");
        }
    }
};
