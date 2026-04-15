<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'patient_code' => $this->patient_code,

            // Personal Info
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'age' => $this->when($this->date_of_birth, fn() => $this->date_of_birth->age),
            'gender' => $this->gender,
            'gender_label' => $this->when($this->gender, fn() => $this->gender === 'male' ? 'ذكر' : 'أنثى'),
            'address' => $this->address,
            'notes' => $this->notes,

            // Status
            'status' => $this->status,
            'status_label' => $this->status === '1' ? 'نشط' : 'غير نشط',
            'is_active' => $this->status === '1',

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'appointments_count' => $this->whenCounted('appointments'),
            'invoices_count' => $this->whenCounted('invoices'),
            'total_spent' => $this->whenLoaded('invoices', fn() => $this->invoices->sum('total')),
            'last_appointment' => $this->whenLoaded('appointments', function () {
                return $this->appointments->sortByDesc('appointment_date')->first()?->only([
                    'id',
                    'appointment_date',
                    'appointment_time',
                    'status'
                ]);
            }),

            // Related Resources
            'appointments' => AppointmentResource::collection($this->whenLoaded('appointments')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'treatment_plans' => TreatmentPlanResource::collection($this->whenLoaded('treatmentPlans')),
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
}
