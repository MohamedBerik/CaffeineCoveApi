<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;

class Payment extends Model
{
    use HasFactory;
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

    protected static function booted()
    {
        static::created(function ($appointment) {
            ActivityLog::create([
                'company_id' => $appointment->company_id,
                'user_id' => auth()->id(),
                'action' => 'created',
                'subject_type' => 'Appointment',
                'subject_id' => $appointment->id,
                'properties' => [
                    'new' => $appointment->toArray()
                ]
            ]);
        });

        static::updated(function ($appointment) {
            ActivityLog::create([
                'company_id' => $appointment->company_id,
                'user_id' => auth()->id(),
                'action' => 'updated',
                'subject_type' => 'Appointment',
                'subject_id' => $appointment->id,
                'properties' => [
                    'old' => $appointment->getOriginal(),
                    'changes' => $appointment->getChanges()
                ]
            ]);
        });

        static::deleted(function ($appointment) {
            ActivityLog::create([
                'company_id' => $appointment->company_id,
                'user_id' => auth()->id(),
                'action' => 'deleted',
                'subject_type' => 'Appointment',
                'subject_id' => $appointment->id,
            ]);
        });
    }
}
