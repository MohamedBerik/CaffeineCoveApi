<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'company_id',
        'stripe_id',
        'card_brand',
        'card_last4',
        'card_exp_month',
        'card_exp_year',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($method) {
            if ($method->is_default) {
                self::where('company_id', $method->company_id)
                    ->update(['is_default' => false]);
            }
        });

        static::updating(function ($method) {
            if ($method->is_default && $method->isDirty('is_default')) {
                self::where('company_id', $method->company_id)
                    ->where('id', '!=', $method->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
