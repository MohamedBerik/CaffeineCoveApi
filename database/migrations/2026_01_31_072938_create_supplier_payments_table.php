<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupplierPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('supplier_payments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('supplier_id')->constrained();

            $table->foreignId('purchase_order_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->decimal('amount', 10, 2);

            $table->string('method')->nullable();

            $table->timestamp('paid_at');

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

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
        Schema::dropIfExists('supplier_payments');
    }
}
