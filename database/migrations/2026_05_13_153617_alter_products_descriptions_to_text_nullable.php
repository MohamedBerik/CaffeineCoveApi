<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // تغيير العمودين إلى TEXT و nullable
            $table->text('description_en')->nullable()->change();
            $table->text('description_ar')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            // استرجاع الحالة السابقة (افترض أنها كانت string(255) و not nullable)
            // هذا يعتمد على التعريف الأصلي; قد تحتاج إلى تعديله وفقاً لحالتك
            $table->string('description_en', 255)->nullable(false)->change();
            $table->string('description_ar', 255)->nullable(false)->change();
        });
    }
};
