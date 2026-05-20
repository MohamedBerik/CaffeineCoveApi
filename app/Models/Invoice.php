<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    public static $hasBranchScope = true;

    // ✅ الثوابت
    const STATUS_UNPAID = 'unpaid';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'branch_id',
        'number',
        'order_id',
        'appointment_id',
        'treatment_plan_id',
        'customer_id',
        'total',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'total'     => 'decimal:2',
    ];

    protected $appends = [
        'total_paid',
        'total_refunded',
        'total_credit_applied',
        'net_paid',
        'remaining',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

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

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function treatmentPlan()
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    // ============ Scopes ============

    public function scopeUnpaid($query)
    {
        return $query->where('status', self::STATUS_UNPAID);
    }

    public function scopePartiallyPaid($query)
    {
        return $query->where('status', self::STATUS_PARTIALLY_PAID);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('issued_at', [$from, $to]);
    }

    // ============ Computed Attributes ============

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()
            ->sum('applied_amount');
    }

    public function getTotalRefundedAttribute(): float
    {
        return (float) DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.invoice_id', $this->id)
            ->where('payment_refunds.applies_to', 'invoice')
            ->sum('payment_refunds.amount');
    }

    public function getTotalCreditAppliedAttribute(): float
    {
        return (float) DB::table('customer_credits')
            ->where('invoice_id', $this->id)
            ->where('type', 'debit')
            ->sum('amount');
    }

    public function getNetPaidAttribute(): float
    {
        return $this->total_paid - $this->total_refunded + $this->total_credit_applied;
    }

    public function getRemainingAttribute(): float
    {
        return max(0, $this->total - $this->net_paid);
    }

    // ============ Helpers ============

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isUnpaid(): bool
    {
        return $this->status === self::STATUS_UNPAID;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_PAID;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function updateStatus(): void
    {
        $netPaid = $this->net_paid;

        if ($netPaid <= 0) {
            $status = self::STATUS_UNPAID;
        } elseif ($netPaid < $this->total) {
            $status = self::STATUS_PARTIALLY_PAID;
        } else {
            $status = self::STATUS_PAID;
        }

        $this->update(['status' => $status]);
    }

    public function markAsPaid(): void
    {
        $this->update(['status' => self::STATUS_PAID]);
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    // ============ Boot - Activity Logging ============

    protected static function booted()
    {
        static::created(function ($invoice) {
            if (auth()->check()) {
                ActivityLog::create([
                    'company_id' => $invoice->company_id,
                    'user_id' => auth()->id(),
                    'action' => 'invoice.created',
                    'subject_type' => Invoice::class,
                    'subject_id' => $invoice->id,
                    'properties' => [
                        'number' => $invoice->number,
                        'customer_id' => $invoice->customer_id,
                        'total' => $invoice->total,
                    ]
                ]);
            }
        });

        static::updated(function ($invoice) {
            if (auth()->check()) {
                $changes = $invoice->getChanges();
                unset($changes['updated_at']);

                if (!empty($changes)) {
                    ActivityLog::create([
                        'company_id' => $invoice->company_id,
                        'user_id' => auth()->id(),
                        'action' => 'invoice.updated',
                        'subject_type' => Invoice::class,
                        'subject_id' => $invoice->id,
                        'properties' => [
                            'changes' => $changes,
                        ]
                    ]);
                }
            }
        });
    }
}
