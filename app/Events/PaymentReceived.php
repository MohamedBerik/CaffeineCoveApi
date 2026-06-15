<?php

namespace App\Events;

use App\Models\BillingInvoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $invoice;

    public function __construct(BillingInvoice $invoice)
    {
        $this->invoice = $invoice;
    }
}
