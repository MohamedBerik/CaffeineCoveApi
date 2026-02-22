<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $db = DB::getDatabaseName();

        $row = DB::selectOne("
            SELECT COUNT(1) AS cnt
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ", [$db, $table, $indexName]);

        return (int)($row->cnt ?? 0) > 0;
    }

    public function up(): void
    {
        if (!Schema::hasTable('accounts')) {
            return;
        }

        // ✅ لو الإندكس موجود خلاص.. اخرج
        if ($this->indexExists('accounts', 'accounts_company_code_unique')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->unique(['company_id', 'code'], 'accounts_company_code_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('accounts')) {
            return;
        }

        if (! $this->indexExists('accounts', 'accounts_company_code_unique')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique('accounts_company_code_unique');
        });
    }
};
