<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'patient_code' => $this->patient_code,

            // Personal Info
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth ? $this->date_of_birth->format('Y-m-d') : null,
            'age' => $this->when($this->date_of_birth, function () {
                return $this->date_of_birth->age;
            }),
            'gender' => $this->gender,
            'gender_label' => $this->when($this->gender, function () {
                return $this->gender === 'male' ? 'ذكر' : 'أنثى';
            }),
            'address' => $this->address,
            'notes' => $this->notes,

            // Status
            'status' => $this->status,
            'status_label' => $this->status === '1' ? 'نشط' : 'غير نشط',
            'is_active' => $this->status === '1',

            // Timestamps
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'appointments_count' => $this->whenLoaded('appointments', function () {
                return $this->appointments->count();
            }),
            'invoices_count' => $this->whenLoaded('invoices', function () {
                return $this->invoices->count();
            }),
            'total_spent' => $this->whenLoaded('invoices', function () {
                return $this->invoices->sum('total');
            }),
            'last_appointment' => $this->whenLoaded('appointments', function () {
                $last = $this->appointments->sortByDesc('appointment_date')->first();
                return $last ? $last->only(['id', 'appointment_date', 'appointment_time', 'status']) : null;
            }),

            // Related Resources
            'appointments' => AppointmentResource::collection($this->whenLoaded('appointments')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'treatment_plans' => TreatmentPlanResource::collection($this->whenLoaded('treatmentPlans')),
        ];
    }

    public function with($request): array
    {
        return [
            'status' => 'success',
        ];
    }
}
