<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class ActivityLog extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'properties'
    ];

    protected $casts = [
        'properties' => 'array'
    ];
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
