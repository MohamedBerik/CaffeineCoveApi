<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;
    const STATUS_TRIAL = 'trial';
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CANCELLED = 'cancelled';

    protected static $hasCompanyColumn = false;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'trial_ends_at',
        'branding',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'branding' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
