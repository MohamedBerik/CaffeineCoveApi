<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('applied_amount', 10, 2)->default(0)->after('amount');
            $table->decimal('credit_amount', 10, 2)->default(0)->after('applied_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['applied_amount', 'credit_amount']);
        });
    }
};
