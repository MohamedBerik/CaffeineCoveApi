<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');

            $table->string('name', 190);
            $table->decimal('default_price', 10, 2)->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->unique(['company_id', 'name']); // يمنع تكرار نفس الإجراء داخل نفس العيادة
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
