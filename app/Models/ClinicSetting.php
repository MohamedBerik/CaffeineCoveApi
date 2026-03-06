<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicSetting extends Model
{
    protected $fillable = [
        'company_id',
        'clinic_name',
        'phone',
        'email',
        'currency',
        'timezone',
        'invoice_prefix',
        'invoice_start_number',
        'next_invoice_number',
        'language',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
