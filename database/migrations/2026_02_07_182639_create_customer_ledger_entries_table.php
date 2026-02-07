<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerLedgerEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('payment_refunds')->nullOnDelete();

            $table->enum('type', ['invoice', 'payment', 'refund']);

            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);

            $table->dateTime('entry_date');

            $table->string('description')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'entry_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_ledger_entries');
    }
}
