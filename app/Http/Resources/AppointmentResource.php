<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id'  => $this->branch_id,

            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->doctor_name,
            'appointment_date' => $this->appointment_date,
            'appointment_time' => $this->appointment_time,
            'appointment_type' => $this->appointment_type,
            'status' => $this->status,
            'notes' => $this->notes,
            'clinical_notes' => $this->clinical_notes,
            'diagnosis' => $this->diagnosis,
            'next_step' => $this->next_step,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'invoice_id' => $this->invoice?->id,
            'invoice_number' => $this->invoice?->number,
            'invoice_status' => $this->invoice?->status,
            'invoice_total' => $this->invoice?->total,
            'treatment_plan_id' => $this->invoice?->treatment_plan_id,
            'patient' => $this->whenLoaded('patient'),
            'doctor' => $this->whenLoaded('doctor'),
            'reminder_status' => $this->reminder_status,
            'last_reminder_at' => $this->last_reminder_at,
            'next_reminder_at' => $this->next_reminder_at,
            'reminder_sent_count' => (int) ($this->reminder_sent_count ?? 0),
            'reminder_stage' => $this->reminder_stage,
        ];
    }
}
