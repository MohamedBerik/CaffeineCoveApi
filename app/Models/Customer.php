<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Customer extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        "name",
        "email",
        "phone",
        "status",
        // patient fields
        'patient_code',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'notes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function ledgerEntries()
    {
        return $this->hasMany(CustomerLedgerEntry::class)
            ->where('company_id', $this->company_id);
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
