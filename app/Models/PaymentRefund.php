<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    protected $fillable = [
        'payment_id',
        'amount',
        'refunded_at',
        'created_by',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
    public function customerLedgerEntry()
    {
        return $this->hasOne(CustomerLedgerEntry::class);
    }
}
