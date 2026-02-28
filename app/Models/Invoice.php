<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'number',
        'order_id',
        'appointment_id',
        'customer_id',
        'total',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'total'     => 'decimal:2',
    ];

    // Optional: computed attributes appear in JSON automatically
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
            'invoice_id',   // FK on payments
            'payment_id',   // FK on payment_refunds
            'appointment_id',   // FK on payment_refunds
            'treatment_plan_id',   // FK on payment_refunds
            'id',           // local key on invoices
            'id'            // local key on payments
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /* =========================
     | Computed attributes
     * ========================= */

    public function getTotalPaidAttribute(): float
    {
        // only applied_amount counts as paid against invoice
        return (float) $this->payments()
            ->where('company_id', $this->company_id)
            ->sum('applied_amount');
    }

    public function getTotalRefundedAttribute(): float
    {
        /**
         * IMPORTANT:
         * Avoid ambiguous "amount" because both payments and payment_refunds have "amount".
         * Use fully-qualified column: payment_refunds.amount
         */
        return (float) DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.invoice_id', $this->id)
            ->where('payments.company_id', $this->company_id)
            ->where('payment_refunds.company_id', $this->company_id)
            ->where('payment_refunds.applies_to', 'invoice')
            ->sum('payment_refunds.amount');
    }

    public function getNetPaidAttribute(): float
    {
        return (float) $this->total_paid - (float) $this->total_refunded;
    }

    public function getRemainingAttribute(): float
    {
        return max(0, (float) $this->total - (float) $this->net_paid);
    }
}
