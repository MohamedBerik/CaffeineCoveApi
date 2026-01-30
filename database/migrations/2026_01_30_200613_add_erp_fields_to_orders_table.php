<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->string('status')->default('pending')->after('customer_id');
            $table->decimal('total', 10, 2)->default(0)->after('status');
            $table->foreignId('created_by')
                ->nullable()
                ->after('total')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->dropForeign(['created_by']);
            $table->dropColumn(['status', 'total', 'created_by']);
        });
    }
};
