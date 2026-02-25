<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // احتياطي: أي NULL تتحول لقيمة افتراضية قبل ما نخليها NOT NULL
        DB::table('appointments')
            ->whereNull('doctor_name')
            ->update(['doctor_name' => 'Unknown']);

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('doctor_name')->nullable(false)->change();
        });

        // ⚠️ لا تضيف unique هنا لأنه موجود بالفعل
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('doctor_name')->nullable()->change();
        });
    }
};
