<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('title_en')->nullable()->change();
            $table->string('title_ar')->nullable()->change();
            $table->text('description_en')->nullable()->change();
            $table->text('description_ar')->nullable()->change();
        });
    }

    // public function down(): void
    // {
    //     Schema::table('orders', function (Blueprint $table) {
    //         $table->string('title_en')->nullable(false)->change();
    //         $table->string('title_ar')->nullable(false)->change();
    //         $table->text('description_en')->nullable(false)->change();
    //         $table->text('description_ar')->nullable(false)->change();
    //     });
    // }
};
