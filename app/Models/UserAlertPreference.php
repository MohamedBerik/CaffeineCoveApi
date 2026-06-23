<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class UserAlertPreference extends Model
{
    use HasFactory;
    // use BelongsToCompanyTrait;

    protected $fillable = [
        'user_id',
        'alert_code',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    // ============ Relationships ============

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
