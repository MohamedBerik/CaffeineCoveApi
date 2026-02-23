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

    protected $casts = [
        'issued_at' => 'datetime',
        'total'     => 'decimal:2',
    ];

    // اختياري: لو حابب الخصائص المحسوبة تظهر في JSON تلقائيًا
    protected $appends = [
        'total_paid',
        'total_refunded',
        'net_paid',
        'remaining',
    ];

    /* =========================
     | Relations
     * ========================= */

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
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
            'invoice_id',
            'payment_id',
            'id',
            'id'
        );
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function customerLedgerEntries()
    {
        return $this->hasMany(CustomerLedgerEntry::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /* =========================
     | Computed attributes
     * ========================= */

    public function getTotalPaidAttribute()
    {
        return $this->payments()->sum('applied_amount');
    }

    public function getTotalRefundedAttribute()
    {
        return $this->refunds()
            ->where('applies_to', 'invoice')
            ->sum('amount');
    }

    public function getNetPaidAttribute()
    {
        return $this->total_paid - $this->total_refunded;
    }

    public function getRemainingAttribute()
    {
        return max(0, (float)$this->total - (float)$this->net_paid);
    }
}
