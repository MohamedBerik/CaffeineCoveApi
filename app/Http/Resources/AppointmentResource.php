<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,

            // Appointment Details
            'appointment_date' => $this->appointment_date ? $this->appointment_date->format('Y-m-d') : null,
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
            'last_reminder_at' => $this->last_reminder_at ? $this->last_reminder_at->toIso8601String() : null,
            'next_reminder_at' => $this->next_reminder_at ? $this->next_reminder_at->toIso8601String() : null,

            // Follow-up Info
            'follow_up_status' => $this->follow_up_status,
            'follow_up_state' => $this->follow_up_state,
            'follow_up_at' => $this->follow_up_at ? $this->follow_up_at->toIso8601String() : null,

            // Timestamps
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships
            'patient' => $this->whenLoaded('patient', function () {
                return [
                    'id' => $this->patient->id,
                    'name' => $this->patient->name,
                    'phone' => $this->patient->phone,
                    'patient_code' => $this->patient->patient_code,
                ];
            }),

            'doctor' => $this->whenLoaded('doctor', function () {
                return [
                    'id' => $this->doctor->id,
                    'name' => $this->doctor->name,
                    'doctor_name' => $this->doctor_name,
                ];
            }),

            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'id' => $this->invoice->id,
                    'number' => $this->invoice->number,
                    'total' => $this->invoice->total,
                    'status' => $this->invoice->status,
                    'treatment_plan_id' => $this->invoice->treatment_plan_id,
                ];
            }),

            'treatment_plan_item' => $this->whenLoaded('treatmentPlanItem', function () {
                return [
                    'id' => $this->treatmentPlanItem->id,
                    'treatment_plan_id' => $this->treatmentPlanItem->treatment_plan_id,
                    'procedure' => $this->treatmentPlanItem->procedure,
                    'tooth_number' => $this->treatmentPlanItem->tooth_number,
                    'price' => $this->treatmentPlanItem->price,
                    'status' => $this->treatmentPlanItem->status,
                ];
            }),
        ];
    }

    public function with($request): array
    {
        return [
            'status' => 'success',
        ];
    }

    private function getTypeLabel(): string
    {
        switch ($this->appointment_type) {
            case 'consultation':
                return 'استشارة';
            case 'treatment':
                return 'علاج';
            case 'follow_up':
                return 'متابعة';
            case 'emergency':
                return 'طارئ';
            default:
                return $this->appointment_type ?? 'غير محدد';
        }
    }

    private function getStatusLabel(): string
    {
        switch ($this->status) {
            case 'scheduled':
                return 'مجدول';
            case 'confirmed':
                return 'مؤكد';
            case 'in_progress':
                return 'قيد التنفيذ';
            case 'completed':
                return 'مكتمل';
            case 'cancelled':
                return 'ملغي';
            case 'no_show':
                return 'لم يحضر';
            default:
                return $this->status ?? 'غير معروف';
        }
    }

    private function getStatusColor(): string
    {
        switch ($this->status) {
            case 'scheduled':
                return 'blue';
            case 'confirmed':
                return 'green';
            case 'in_progress':
                return 'orange';
            case 'completed':
                return 'gray';
            case 'cancelled':
                return 'red';
            case 'no_show':
                return 'red';
            default:
                return 'gray';
        }
    }
}
