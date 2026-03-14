<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->unsignedInteger('planned_sessions')
                ->default(1)
                ->after('status');

            $table->unsignedInteger('completed_sessions')
                ->default(0)
                ->after('planned_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropColumn(['planned_sessions', 'completed_sessions']);
        });
    }
};
