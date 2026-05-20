<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class CustomerLedgerEntry extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    public static $hasBranchScope = true;

    // ✅ الثوابت
    const TYPE_INVOICE = 'invoice';
    const TYPE_PAYMENT = 'payment';
    const TYPE_REFUND_INVOICE = 'refund_invoice';
    const TYPE_REFUND_CREDIT = 'refund_credit';
    const TYPE_CREDIT_APPLY = 'credit_apply';

    protected $fillable = [
        'company_id',
        'branch_id',
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
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

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
        return $this->belongsTo(PaymentRefund::class, 'refund_id');
    }

    // ============ Scopes ============

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('entry_date', [$from, $to]);
    }

    public function scopeBeforeDate($query, $date)
    {
        return $query->where('entry_date', '<', $date);
    }

    // ============ Helpers ============

    public function getNetAmountAttribute(): float
    {
        return $this->debit - $this->credit;
    }

    public function isDebit(): bool
    {
        return $this->debit > 0;
    }

    public function isCredit(): bool
    {
        return $this->credit > 0;
    }
}
