<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerLedgerEntry extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_id',
        'payment_id',
        'refund_id',
        'type',
        'debit',
        'credit',
        'entry_date',
        'description',
    ];

    protected $casts = [
        'entry_date' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function paymentRefund()
    {
        return $this->belongsTo(PaymentRefund::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
