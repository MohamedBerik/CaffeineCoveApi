<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentRefund extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const APPLIES_TO_INVOICE = 'invoice';
    const APPLIES_TO_CREDIT = 'credit';

    protected $fillable = [
        'company_id',
        'branch_id',
        'payment_id',
        'amount',
        'applies_to',
        'refunded_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    protected $attributes = [
        'applies_to' => self::APPLIES_TO_INVOICE,
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function customerLedgerEntry()
    {
        return $this->hasOne(CustomerLedgerEntry::class, 'refund_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============ Scopes ============

    public function scopeForPayment($query, $paymentId)
    {
        return $query->where('payment_id', $paymentId);
    }

    public function scopeForInvoice($query)
    {
        return $query->where('applies_to', self::APPLIES_TO_INVOICE);
    }

    public function scopeForCredit($query)
    {
        return $query->where('applies_to', self::APPLIES_TO_CREDIT);
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('refunded_at', [$from, $to]);
    }

    // ============ Accessors ============

    public function getAppliesToLabelAttribute(): string
    {
        return $this->applies_to === self::APPLIES_TO_INVOICE ? 'Invoice' : 'Credit';
    }

    // ============ Helpers ============

    public function isForInvoice(): bool
    {
        return $this->applies_to === self::APPLIES_TO_INVOICE;
    }

    public function isForCredit(): bool
    {
        return $this->applies_to === self::APPLIES_TO_CREDIT;
    }

    public function getInvoiceAttribute()
    {
        return $this->payment?->invoice;
    }

    public function getCustomerAttribute()
    {
        return $this->payment?->invoice?->customer;
    }
}
