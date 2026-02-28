<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class TreatmentPlan extends Model
{
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
}
