<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class TreatmentPlanItem extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'treatment_plan_id',
        'procedure_id',
        'procedure',
        'tooth_number',
        'surface',
        'notes',
        'price',
        'status',
        'appointment_id',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(\App\Models\Appointment::class);
    }

    public function plan()
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }
    public function procedureRef()
    {
        return $this->belongsTo(Procedure::class, 'procedure_id');
    }
}
