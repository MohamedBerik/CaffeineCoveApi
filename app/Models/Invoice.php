<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'number',
        'order_id',
        'customer_id',
        'total',
        'status',
        'issued_at'
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function refunds()
    {
        return $this->hasManyThrough(
            \App\Models\PaymentRefund::class,
            \App\Models\Payment::class
        );
    }
}
