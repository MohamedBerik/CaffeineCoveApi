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

    /* =====================================================
     | Relations (company safe)
     * ===================================================== */

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)
            ->where('company_id', $this->company_id);
    }

    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class)
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
