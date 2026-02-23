<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Payment extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'amount',
        'method',
        'paid_at',
        'received_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
