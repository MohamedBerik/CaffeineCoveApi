<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            'number' => $this->number,

            // Amounts
            'total' => (float) $this->total,
            'total_formatted' => number_format($this->total, 2) . ' EGP',

            // Payment Status
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Computed Amounts
            'total_paid' => (float) ($this->total_paid ?? 0),
            'total_refunded' => (float) ($this->total_refunded ?? 0),
            'total_credit_applied' => (float) ($this->total_credit_applied ?? 0),
            'net_paid' => (float) ($this->net_paid ?? 0),
            'remaining' => (float) ($this->remaining ?? 0),
            'remaining_formatted' => number_format($this->remaining ?? 0, 2) . ' EGP',

            // Flags
            'is_paid' => $this->isPaid(),
            'is_partially_paid' => $this->isPartiallyPaid(),
            'is_unpaid' => $this->isUnpaid(),

            // Dates
            'issued_at' => $this->issued_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relations IDs
            'customer_id' => $this->customer_id,
            'appointment_id' => $this->appointment_id,
            'order_id' => $this->order_id,
            'treatment_plan_id' => $this->treatment_plan_id,

            // Relationships (when loaded)
            'customer' => $this->whenLoaded('customer', fn() => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),

            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),

            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'payments_count' => $this->whenCounted('payments'),

            'appointment' => $this->whenLoaded('appointment', fn() => [
                'id' => $this->appointment->id,
                'appointment_date' => $this->appointment->appointment_date?->format('Y-m-d'),
                'appointment_time' => $this->appointment->appointment_time,
                'status' => $this->appointment->status,
            ]),

            'treatment_plan' => $this->whenLoaded('treatmentPlan', fn() => [
                'id' => $this->treatmentPlan->id,
                'title' => $this->treatmentPlan->title,
                'status' => $this->treatmentPlan->status,
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
            'unpaid' => 'غير مدفوعة',
            'partially_paid' => 'مدفوعة جزئيًا',
            'paid' => 'مدفوعة',
            'cancelled' => 'ملغية',
            default => $this->status ?? 'غير معروف',
        };
    }

    /**
     * Get status color for UI.
     */
    private function getStatusColor(): string
    {
        return match ($this->status) {
            'unpaid' => 'red',
            'partially_paid' => 'orange',
            'paid' => 'green',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }
}
