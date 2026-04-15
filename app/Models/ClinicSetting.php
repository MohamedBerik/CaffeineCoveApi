<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class ClinicSetting extends Model
{
    // ✅ الـ Trait - ضروري
    use BelongsToCompanyTrait;

    // ✅ Performance fix - ضروري
    protected static $hasCompanyColumn = true;

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

    /**
     * العلاقة مع Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
