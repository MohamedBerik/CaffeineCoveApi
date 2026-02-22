<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // private string $table = 'order_items';
    // private string $fkName = 'order_items_company_id_foreign';

    // private function fkExists(): bool
    // {
    //     // REFERENTIAL_CONSTRAINTS أدق من TABLE_CONSTRAINTS لموضوع الـ FK
    //     $rows = DB::select("
    //         SELECT CONSTRAINT_NAME
    //         FROM information_schema.REFERENTIAL_CONSTRAINTS
    //         WHERE CONSTRAINT_SCHEMA = DATABASE()
    //           AND CONSTRAINT_NAME = ?
    //         LIMIT 1
    //     ", [$this->fkName]);

    //     return !empty($rows);
    // }

    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('company_id', 'fk_order_items_company_id')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign('fk_order_items_company_id');
        });
    }
};
