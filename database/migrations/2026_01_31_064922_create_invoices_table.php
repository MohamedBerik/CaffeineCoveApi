<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {

            $table->id();

            $table->string('number')->unique();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->foreignId('customer_id')->constrained('users');

            $table->decimal('total', 10, 2);

            $table->string('status')->default('unpaid');

            $table->timestamp('issued_at')->nullable();

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
        Schema::dropIfExists('invoices');
    }
}
