<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
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

            // Appointment Details
            'appointment_date' => $this->appointment_date?->format('Y-m-d'),
            'appointment_time' => $this->appointment_time,
            'appointment_type' => $this->appointment_type,
            'appointment_type_label' => $this->getTypeLabel(),

            // Status
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Computed Flags
            'is_upcoming' => $this->is_upcoming ?? false,
            'is_past' => $this->is_past ?? false,
            'can_be_modified' => $this->can_be_modified ?? false,

            // Notes & Clinical Info
            'notes' => $this->notes,
            'clinical_notes' => $this->clinical_notes,
            'diagnosis' => $this->diagnosis,
            'next_step' => $this->next_step,

            // Reminder Info
            'reminder_status' => $this->reminder_status,
            'reminder_stage' => $this->reminder_stage,
            'reminder_sent_count' => $this->reminder_sent_count,
            'last_reminder_at' => $this->last_reminder_at?->toISOString(),
            'next_reminder_at' => $this->next_reminder_at?->toISOString(),

            // Follow-up Info
            'follow_up_status' => $this->follow_up_status,
            'follow_up_state' => $this->follow_up_state,
            'follow_up_at' => $this->follow_up_at?->toISOString(),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'patient' => $this->whenLoaded('patient', fn() => [
                'id' => $this->patient->id,
                'name' => $this->patient->name,
                'phone' => $this->patient->phone,
                'patient_code' => $this->patient->patient_code,
            ]),

            'doctor' => $this->whenLoaded('doctor', fn() => [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
                'doctor_name' => $this->doctor_name,
            ]),

            'invoice' => $this->whenLoaded('invoice', fn() => [
                'id' => $this->invoice->id,
                'number' => $this->invoice->number,
                'total' => $this->invoice->total,
                'status' => $this->invoice->status,
                'treatment_plan_id' => $this->invoice->treatment_plan_id,
            ]),

            'treatment_plan_item' => $this->whenLoaded('treatmentPlanItem', fn() => [
                'id' => $this->treatmentPlanItem->id,
                'treatment_plan_id' => $this->treatmentPlanItem->treatment_plan_id,
                'procedure' => $this->treatmentPlanItem->procedure,
                'tooth_number' => $this->treatmentPlanItem->tooth_number,
                'price' => $this->treatmentPlanItem->price,
                'status' => $this->treatmentPlanItem->status,
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
     * Get appointment type label.
     */
    private function getTypeLabel(): string
    {
        return match ($this->appointment_type) {
            'consultation' => 'استشارة',
            'treatment' => 'علاج',
            'follow_up' => 'متابعة',
            'emergency' => 'طارئ',
            default => $this->appointment_type ?? 'غير محدد',
        };
    }

    /**
     * Get status label in Arabic.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'scheduled' => 'مجدول',
            'confirmed' => 'مؤكد',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
            'no_show' => 'لم يحضر',
            default => $this->status ?? 'غير معروف',
        };
    }

    /**
     * Get status color for UI.
     */
    private function getStatusColor(): string
    {
        return match ($this->status) {
            'scheduled' => 'blue',
            'confirmed' => 'green',
            'in_progress' => 'orange',
            'completed' => 'gray',
            'cancelled' => 'red',
            'no_show' => 'red',
            default => 'gray',
        };
    }
}
