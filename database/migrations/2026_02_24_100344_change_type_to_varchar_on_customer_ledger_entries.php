
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE `customer_ledger_entries` MODIFY `type` varchar(50) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `customer_ledger_entries` MODIFY `type` ENUM('invoice','payment','refund') NOT NULL");
    }
};
