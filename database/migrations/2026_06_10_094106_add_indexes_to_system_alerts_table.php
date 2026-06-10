<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('system_alerts', function (Blueprint $table) {
            // التحقق من وجود الفهارس قبل إضافتها
            if (!$this->indexExists('system_alerts', ['company_id', 'resolved_at'])) {
                $table->index(['company_id', 'resolved_at']);
            }
            if (!$this->indexExists('system_alerts', ['company_id', 'acknowledged_at'])) {
                $table->index(['company_id', 'acknowledged_at']);
            }
            if (!$this->indexExists('system_alerts', ['company_id', 'type'])) {
                $table->index(['company_id', 'type']);
            }
            if (!$this->indexExists('system_alerts', ['company_id', 'priority'])) {
                $table->index(['company_id', 'priority']);
            }
            if (!$this->indexExists('system_alerts', ['company_id', 'code'])) {
                $table->index(['company_id', 'code']);
            }
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists($table, $columns)
    {
        $indexName = $this->getIndexName($table, $columns);
        $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($result) > 0;
    }

    /**
     * Generate the index name like Laravel does.
     */
    private function getIndexName($table, $columns)
    {
        return $table . '_' . implode('_', $columns) . '_index';
    }
};
