<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class CustomerLedgerEntry extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
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
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class)
            ->where('company_id', $this->company_id);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)
            ->where('company_id', $this->company_id);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class)
            ->where('company_id', $this->company_id);
    }

    public function paymentRefund()
    {
        return $this->belongsTo(PaymentRefund::class)
            ->where('company_id', $this->company_id);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
