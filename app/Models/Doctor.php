<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'phone',
        'email',
        'is_active',
        'work_start',
        'work_end',
        'slot_minutes',
        'created_by'
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }
}
