<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');

            $table->string('key', 100);          // Idempotency-Key
            $table->string('endpoint', 190);     // e.g. POST /api/erp/payments/{id}/refund
            $table->string('request_hash', 64);  // sha256(payload) to detect misuse

            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response_body')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'key', 'endpoint'], 'idem_company_key_endpoint_unique');
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
