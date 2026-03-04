<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentRefund extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'payment_id',
        'amount',
        'applies_to',
        'refunded_at',
        'created_by'
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id'); // ✅ بدون where
    }

    public function customerLedgerEntry()
    {
        return $this->hasOne(CustomerLedgerEntry::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
