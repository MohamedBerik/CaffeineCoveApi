<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Customer extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;


    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const STATUS_ACTIVE = '1';
    const STATUS_INACTIVE = '0';

    const GENDER_MALE = 'male';
    const GENDER_FEMALE = 'female';

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'email',
        'phone',
        'status',
        'patient_code',
        'date_of_birth',
        'gender',
        'address',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(CustomerLedgerEntry::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function treatmentPlans()
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    public function dentalRecords()
    {
        return $this->hasMany(DentalRecord::class);
    }

    public function radiologies()
    {
        return $this->hasMany(PatientRadiology::class);
    }

    // ============ Scopes ============

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('patient_code', 'like', "%{$term}%");
        });
    }

    // ============ Helpers ============

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getAgeAttribute(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }
        return $this->date_of_birth->age;
    }

    public function getFullAddressAttribute(): string
    {
        return $this->address ?: 'N/A';
    }

    // ============ Boot - Activity Logging ============

    protected static function booted()
    {
        static::created(function ($customer) {
            if (auth()->check()) {
                ActivityLog::create([
                    'company_id' => $customer->company_id,
                    'user_id' => auth()->id(),
                    'action' => 'customer.created',
                    'subject_type' => Customer::class,
                    'subject_id' => $customer->id,
                    'properties' => [
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                    ]
                ]);
            }
        });

        static::updated(function ($customer) {
            if (auth()->check()) {
                $changes = $customer->getChanges();
                unset($changes['updated_at']);

                if (!empty($changes)) {
                    ActivityLog::create([
                        'company_id' => $customer->company_id,
                        'user_id' => auth()->id(),
                        'action' => 'customer.updated',
                        'subject_type' => Customer::class,
                        'subject_id' => $customer->id,
                        'properties' => [
                            'changes' => $changes,
                        ]
                    ]);
                }
            }
        });

        static::deleted(function ($customer) {
            if (auth()->check()) {
                ActivityLog::create([
                    'company_id' => $customer->company_id,
                    'user_id' => auth()->id(),
                    'action' => 'customer.deleted',
                    'subject_type' => Customer::class,
                    'subject_id' => $customer->id,
                    'properties' => [
                        'name' => $customer->name,
                    ]
                ]);
            }
        });
    }
}
