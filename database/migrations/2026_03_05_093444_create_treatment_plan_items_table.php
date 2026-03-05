<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('treatment_plan_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('treatment_plan_id');

            $table->string('procedure', 190);          // e.g. Root Canal, Filling
            $table->string('tooth_number', 10)->nullable(); // e.g. 11..48 (اختياري مبدئيًا)
            $table->string('surface', 50)->nullable(); // e.g. occlusal/mesial/distal
            $table->text('notes')->nullable();

            $table->decimal('price', 10, 2)->default(0);

            $table->timestamps();

            $table->index(['company_id', 'treatment_plan_id']);

            // روابط FK (لو تحب تشغلها)
            // $table->foreign('treatment_plan_id')->references('id')->on('treatment_plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_items');
    }
};
