<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentPlanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,

            // Plan Details
            'title' => $this->title,
            'notes' => $this->notes,

            // Financial
            'total_cost' => (float) $this->total_cost,
            'total_cost_formatted' => number_format($this->total_cost, 2) . ' EGP',
            'total_paid' => (float) ($this->total_paid ?? 0),
            'total_paid_formatted' => number_format($this->total_paid ?? 0, 2) . ' EGP',
            'total_invoiced' => (float) ($this->total_invoiced ?? 0),
            'remaining' => (float) ($this->remaining ?? 0),
            'remaining_formatted' => number_format($this->remaining ?? 0, 2) . ' EGP',
            'progress_percentage' => (float) ($this->progress_percentage ?? 0),

            // Status
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Flags
            'is_active' => $this->isActive(),
            'is_completed' => $this->isCompleted(),
            'is_cancelled' => $this->isCancelled(),
            'is_fully_paid' => $this->isFullyPaid(),
            'can_be_modified' => $this->canBeModified(),

            // Counts
            'items_count' => $this->whenLoaded('items', function () {
                return $this->items->count();
            }),
            'completed_items_count' => (int) ($this->completed_items_count ?? 0),
            'invoices_count' => $this->whenLoaded('invoices', function () {
                return $this->invoices->count();
            }),

            // Dates
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
                    'patient_code' => $this->customer->patient_code,
                ];
            }),

            'items' => TreatmentPlanItemResource::collection($this->whenLoaded('items')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
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
            case 'active':
                return 'نشط';
            case 'completed':
                return 'مكتمل';
            case 'cancelled':
                return 'ملغي';
            default:
                return $this->status ?? 'غير معروف';
        }
    }

    private function getStatusColor(): string
    {
        switch ($this->status) {
            case 'active':
                return 'green';
            case 'completed':
                return 'blue';
            case 'cancelled':
                return 'red';
            default:
                return 'gray';
        }
    }
}
