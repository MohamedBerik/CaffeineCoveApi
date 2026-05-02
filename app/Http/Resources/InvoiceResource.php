<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
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
            'issued_at' => $this->issued_at ? $this->issued_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relations IDs
            'customer_id' => $this->customer_id,
            'appointment_id' => $this->appointment_id,
            'order_id' => $this->order_id,
            'treatment_plan_id' => $this->treatment_plan_id,

            // Relationships (when loaded)
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
                ];
            }),

            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenLoaded('items', function () {
                return $this->items->count();
            }),

            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'payments_count' => $this->whenLoaded('payments', function () {
                return $this->payments->count();
            }),

            'appointment' => $this->whenLoaded('appointment', function () {
                return [
                    'id' => $this->appointment->id,
                    'appointment_date' => $this->appointment->appointment_date ? $this->appointment->appointment_date->format('Y-m-d') : null,
                    'appointment_time' => $this->appointment->appointment_time,
                    'status' => $this->appointment->status,
                ];
            }),

            'treatment_plan' => $this->whenLoaded('treatmentPlan', function () {
                return [
                    'id' => $this->treatmentPlan->id,
                    'title' => $this->treatmentPlan->title,
                    'status' => $this->treatmentPlan->status,
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

    private function getStatusLabel(): string
    {
        switch ($this->status) {
            case 'unpaid':
                return 'غير مدفوعة';
            case 'partially_paid':
                return 'مدفوعة جزئيًا';
            case 'paid':
                return 'مدفوعة';
            case 'cancelled':
                return 'ملغية';
            default:
                return $this->status ?? 'غير معروف';
        }
    }

    private function getStatusColor(): string
    {
        switch ($this->status) {
            case 'unpaid':
                return 'red';
            case 'partially_paid':
                return 'orange';
            case 'paid':
                return 'green';
            case 'cancelled':
                return 'gray';
            default:
                return 'gray';
        }
    }
}
