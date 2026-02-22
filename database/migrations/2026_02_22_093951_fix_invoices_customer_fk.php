

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            // drop wrong FK first
            $table->dropForeign(['customer_id']);

            // ✅ correct FK
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnUpdate()
                ->restrictOnDelete(); // أو ->nullOnDelete() لو customer_id nullable
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->dropForeign(['customer_id']);

            // rollback to old (wrong) if you want
            $table->foreign('customer_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }
};
