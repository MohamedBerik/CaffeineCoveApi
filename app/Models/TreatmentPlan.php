<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class TreatmentPlan extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'customer_id',
        'title',
        'notes',
        'total_cost',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'treatment_plan_id');
    }

    public function items()
    {
        return $this->hasMany(TreatmentPlanItem::class, 'treatment_plan_id')
            ->where('company_id', $this->company_id);
    }
}
