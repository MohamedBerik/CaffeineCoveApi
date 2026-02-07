<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'amount',
        'method',
        'paid_at',
        'received_by'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    public function refunds()
    {
        return $this->hasMany(\App\Models\PaymentRefund::class);
    }
    public function customerLedgerEntry()
    {
        return $this->hasOne(CustomerLedgerEntry::class);
    }
}
