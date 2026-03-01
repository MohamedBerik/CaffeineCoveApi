<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->string('name', 190);
            $table->string('phone', 50)->nullable();
            $table->string('email', 190)->nullable();

            $table->boolean('is_active')->default(true);

            // Working hours (V1 simple)
            $table->string('work_start', 5)->default('09:00'); // HH:mm
            $table->string('work_end', 5)->default('17:00');   // HH:mm
            $table->unsignedSmallInteger('slot_minutes')->default(30);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->unique(['company_id', 'name']); // V1: اسم الطبيب unique لكل شركة (ممكن تغيّره لاحقًا)
        });

        // FK optional (لو عندك companies table)
        // Schema::table('doctors', fn(Blueprint $t) => $t->foreign('company_id')->references('id')->on('companies'));
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
