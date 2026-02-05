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

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }

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
            PaymentRefund::class,
            Payment::class,
            'invoice_id',   // Foreign key on Payment table
            'payment_id',   // Foreign key on PaymentRefund table
            'id',           // Local key on Invoice
            'id'            // Local key on Payment
        );
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function getTotalPaidAttribute()
    {
        return $this->payments()->sum('amount');
    }

    public function getTotalRefundedAttribute()
    {
        return $this->refunds()->sum('amount');
    }

    public function getNetPaidAttribute()
    {
        return $this->total_paid - $this->total_refunded;
    }

    public function getRemainingAttribute()
    {
        return max(0, $this->total - $this->net_paid);
    }
}
