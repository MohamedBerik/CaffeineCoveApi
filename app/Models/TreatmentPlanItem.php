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
        'planned_sessions',
        'completed_sessions',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'planned_sessions' => 'integer',
        'completed_sessions' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'remaining_sessions',
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

    public function getRemainingSessionsAttribute()
    {
        $planned = (int) ($this->planned_sessions ?? 1);
        $completed = (int) ($this->completed_sessions ?? 0);

        return max($planned - $completed, 0);
    }
}
