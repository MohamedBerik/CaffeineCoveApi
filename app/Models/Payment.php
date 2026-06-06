<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const METHOD_CASH = 'cash';
    const METHOD_CARD = 'card';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CHECK = 'check';
    const METHOD_OTHER = 'other';

    protected $fillable = [
        'company_id',
        'branch_id',
        'invoice_id',
        'amount',
        'applied_amount',
        'credit_amount',
        'method',
        'paid_at',
        'received_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected $attributes = [
        'applied_amount' => 0,
        'credit_amount' => 0,
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class, 'payment_id');
    }

    public function customerLedgerEntries()
    {
        return $this->hasMany(CustomerLedgerEntry::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // ============ Scopes ============

    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', $method);
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('paid_at', [$from, $to]);
    }

    // ============ Accessors ============

    public function getAvailableInvoiceRefundAttribute(): float
    {
        $refunded = $this->refunds()
            ->where('applies_to', 'invoice')
            ->sum('amount');

        return max(0, $this->applied_amount - $refunded);
    }

    public function getAvailableCreditRefundAttribute(): float
    {
        $refunded = $this->refunds()
            ->where('applies_to', 'credit')
            ->sum('amount');

        return max(0, $this->credit_amount - $refunded);
    }

    public function getTotalRefundedAttribute(): float
    {
        return $this->refunds()->sum('amount');
    }

    public function getNetPaymentAttribute(): float
    {
        return $this->amount - $this->total_refunded;
    }

    // ============ Helpers ============

    public function hasCredit(): bool
    {
        return $this->credit_amount > 0;
    }

    public function canRefundInvoice(): bool
    {
        return $this->available_invoice_refund > 0;
    }

    public function canRefundCredit(): bool
    {
        return $this->available_credit_refund > 0;
    }

    public function isFullyRefunded(): bool
    {
        return $this->total_refunded >= $this->amount;
    }

    // ============ Boot - Activity Logging ============

    protected static function booted()
    {
        static::creating(function ($payment) {
            // توزيع المبلغ تلقائيًا لو مش محدد
            if (!$payment->applied_amount && !$payment->credit_amount) {
                $payment->applied_amount = $payment->amount;
                $payment->credit_amount = 0;
            }
        });

        static::created(function ($payment) {
            ActivityLogger::log(
                $payment->company_id,
                auth()->user(),
                'payment.created',
                Payment::class,
                $payment->id,
                [
                    'invoice_id' => $payment->invoice_id,
                    'amount' => $payment->amount,
                    'method' => $payment->method,
                ],
                $payment->branch_id
            );
        });

        static::updated(function ($payment) {
            if (auth()->check()) {
                $changes = $payment->getChanges();
                unset($changes['updated_at']);

                if (!empty($changes)) {
                    ActivityLogger::log(
                        $payment->company_id,
                        auth()->user(),
                        'payment.updated',
                        Payment::class,
                        $payment->id,
                        [
                            'changes' => $changes,
                        ],
                        $payment->branch_id
                    );
                }
            }
        });

        static::deleted(function ($payment) {
            if (auth()->check()) {
                ActivityLogger::log(
                    $payment->company_id,
                    auth()->user(),
                    'payment.deleted',
                    Payment::class,
                    $payment->id,
                    [
                        'invoice_id' => $payment->invoice_id,
                        'amount' => $payment->amount,
                    ],
                    $payment->branch_id
                );
            }
        });
    }
}
