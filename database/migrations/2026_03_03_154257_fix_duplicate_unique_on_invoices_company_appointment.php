<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        // Check if both indexes exist
        $indexes = DB::select("SHOW INDEX FROM invoices");
        $names = array_values(array_unique(array_map(fn($r) => $r->Key_name, $indexes)));

        // Drop the duplicate (keep invoices_company_appointment_unique)
        if (
            in_array('uniq_company_appointment_invoice', $names, true) &&
            in_array('invoices_company_appointment_unique', $names, true)
        ) {
            DB::statement("ALTER TABLE invoices DROP INDEX uniq_company_appointment_invoice");
        }
    }

    public function down(): void
    {
        // No-op
    }
};
