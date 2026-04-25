<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class DatabaseTest extends TestCase
{
    public function test_core_tables_exist()
    {
        $tables = [
            'companies',
            'users',
            'subscriptions',
            'billing_invoices',
            'plans',
            'webhook_logs',
            'failed_jobs',
            'activity_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Table {$table} does not exist"
            );
        }
    }

    public function test_subscriptions_has_required_columns()
    {
        $columns = Schema::getColumnListing('subscriptions');

        $required = [
            'id',
            'company_id',
            'plan_id',
            'status',
            'amount',
            'starts_at',
            'ends_at',
            'grace_period_ends_at',
            'transaction_id',
        ];

        foreach ($required as $column) {
            $this->assertTrue(
                in_array($column, $columns),
                "Column {$column} missing in subscriptions"
            );
        }
    }
}
