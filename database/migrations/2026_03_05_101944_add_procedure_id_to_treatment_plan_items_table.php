<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('treatment_plan_items')) {
            return;
        }

        Schema::table('treatment_plan_items', function (Blueprint $table) {
            if (!Schema::hasColumn('treatment_plan_items', 'procedure_id')) {
                $table->unsignedBigInteger('procedure_id')->nullable()->after('treatment_plan_id');
                $table->index(['procedure_id']);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('treatment_plan_items')) {
            return;
        }

        Schema::table('treatment_plan_items', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_plan_items', 'procedure_id')) {
                $table->dropColumn('procedure_id');
            }
        });
    }
};
