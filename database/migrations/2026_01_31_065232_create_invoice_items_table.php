<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoice_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->foreignId('product_id')->constrained();

            $table->unsignedInteger('quantity');

            $table->decimal('unit_price', 10, 2);

            $table->decimal('total', 10, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoice_items');
    }
}
