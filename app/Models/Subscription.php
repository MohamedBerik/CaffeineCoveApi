<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    public static $hasBranchScope = true;

    protected $fillable = [
        'company_id',
        'branch_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'grace_period_ends_at',
        'amount',
        'billing_cycle',
        'status',
        'payment_gateway',
        'payment_token',
        'payment_intent_id',
        'payment_method',
        'transaction_id',
        'notes',
        'suspended_at',
        'suspension_reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',           // الأفضل datetime بدل date
        'ends_at' => 'datetime',
        'grace_period_ends_at' => 'datetime', // ✅ أضفناها
        'suspended_at' => 'datetime',         // ✅ أضفناها
        'amount' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
