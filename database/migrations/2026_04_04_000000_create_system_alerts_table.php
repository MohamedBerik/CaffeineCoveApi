<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');

            $table->string('code'); // REMINDER_FAILED_SPIKE
            $table->string('type'); // danger / warning
            $table->string('priority'); // high / medium

            $table->string('message');
            $table->json('meta')->nullable();

            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'code']);
            $table->index(['company_id', 'code', 'resolved_at']);
            $table->index(['company_id', 'acknowledged_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('system_alerts');
    }
};
