<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentPlanItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'treatment_plan_id' => $this->treatment_plan_id,
            'procedure_id' => $this->procedure_id,

            // Procedure Details
            'procedure' => $this->procedure,
            'tooth_number' => $this->tooth_number,
            'tooth_with_surface' => $this->tooth_with_surface,
            'surface' => $this->surface,
            'notes' => $this->notes,

            // Financial
            'price' => (float) $this->price,
            'price_formatted' => number_format($this->price, 2) . ' EGP',
            'total_price' => (float) $this->total_price,
            'total_price_formatted' => number_format($this->total_price, 2) . ' EGP',

            // Sessions
            'planned_sessions' => (int) $this->planned_sessions,
            'completed_sessions' => (int) $this->completed_sessions,
            'remaining_sessions' => (int) $this->remaining_sessions,

            // Status
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Flags
            'is_planned' => $this->isPlanned(),
            'is_in_progress' => $this->isInProgress(),
            'is_completed' => $this->isCompleted(),
            'can_be_started' => $this->canBeStarted(),
            'can_be_completed' => $this->canBeCompleted(),

            // Progress
            'progress_percentage' => (float) $this->progress_percentage,

            // Relations IDs
            'appointment_id' => $this->appointment_id,

            // Dates
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'procedure_ref' => $this->whenLoaded('procedureRef', fn() => [
                'id' => $this->procedureRef->id,
                'name' => $this->procedureRef->name,
                'default_price' => $this->procedureRef->default_price,
            ]),

            'treatment_plan' => $this->whenLoaded('plan', fn() => [
                'id' => $this->plan->id,
                'title' => $this->plan->title,
                'customer_id' => $this->plan->customer_id,
                'status' => $this->plan->status,
            ]),

            'appointment' => $this->whenLoaded('appointment', fn() => [
                'id' => $this->appointment->id,
                'appointment_date' => $this->appointment->appointment_date?->format('Y-m-d'),
                'appointment_time' => $this->appointment->appointment_time,
                'status' => $this->appointment->status,
            ]),

            'dental_record' => $this->whenLoaded('dentalRecord', fn() => [
                'id' => $this->dentalRecord->id,
                'tooth_number' => $this->dentalRecord->tooth_number,
                'status' => $this->dentalRecord->status,
            ]),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with($request): array
    {
        return [
            'status' => 'success',
        ];
    }

    /**
     * Get status label in Arabic.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'planned' => 'مخطط',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتمل',
            default => $this->status ?? 'غير معروف',
        };
    }

    /**
     * Get status color for UI.
     */
    private function getStatusColor(): string
    {
        return match ($this->status) {
            'planned' => 'blue',
            'in_progress' => 'orange',
            'completed' => 'green',
            default => 'gray',
        };
    }
}
