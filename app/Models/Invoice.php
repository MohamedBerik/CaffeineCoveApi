<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Invoice extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'number',
        'order_id',
        'customer_id',
        'total',
        'status',
        'issued_at'
    ];

    /* =====================================================
     | Relations (company safe)
     * ===================================================== */

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')
            ->where('company_id', $this->company_id);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)
            ->where('company_id', $this->company_id);
    }

    public function order()
    {
        return $this->belongsTo(Order::class)
            ->where('company_id', $this->company_id);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)
            ->where('company_id', $this->company_id);
    }

    public function refunds()
    {
        return $this->hasManyThrough(
            PaymentRefund::class,
            Payment::class,
            'invoice_id',   // FK on payments table
            'payment_id',   // FK on payment_refunds table
            'id',
            'id'
        )
            ->where('payments.company_id', $this->company_id)
            ->where('payment_refunds.company_id', $this->company_id);
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source')
            ->where('company_id', $this->company_id);
    }

    public function customerLedgerEntries()
    {
        return $this->hasMany(CustomerLedgerEntry::class)
            ->where('company_id', $this->company_id);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /* =====================================================
     | Computed attributes
     * ===================================================== */

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
