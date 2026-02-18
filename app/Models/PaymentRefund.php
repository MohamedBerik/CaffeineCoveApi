<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class PaymentRefund extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'payment_id',
        'amount',
        'refunded_at',
        'created_by',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class)
            ->where('company_id', $this->company_id);
    }
    public function customerLedgerEntry()
    {
        return $this->hasOne(CustomerLedgerEntry::class)
            ->where('company_id', $this->company_id);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
