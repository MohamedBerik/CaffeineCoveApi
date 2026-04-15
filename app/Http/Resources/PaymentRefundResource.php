<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentRefundResource extends JsonResource
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
            'payment_id' => $this->payment_id,

            // Amount
            'amount' => (float) $this->amount,
            'amount_formatted' => number_format($this->amount, 2) . ' EGP',

            // Refund Type
            'applies_to' => $this->applies_to,
            'applies_to_label' => $this->getAppliesToLabel(),

            // Flags
            'is_for_invoice' => $this->isForInvoice(),
            'is_for_credit' => $this->isForCredit(),

            // Relations IDs
            'created_by' => $this->created_by,

            // Dates
            'refunded_at' => $this->refunded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'payment' => $this->whenLoaded('payment', fn() => [
                'id' => $this->payment->id,
                'amount' => $this->payment->amount,
                'method' => $this->payment->method,
                'paid_at' => $this->payment->paid_at?->toISOString(),
            ]),

            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'invoice' => $this->whenLoaded('payment.invoice', fn() => [
                'id' => $this->payment->invoice->id,
                'number' => $this->payment->invoice->number,
            ]),

            'customer' => $this->whenLoaded('payment.invoice.customer', fn() => [
                'id' => $this->payment->invoice->customer->id,
                'name' => $this->payment->invoice->customer->name,
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
     * Get applies_to label in Arabic.
     */
    private function getAppliesToLabel(): string
    {
        return match ($this->applies_to) {
            'invoice' => 'استرداد من الفاتورة',
            'credit' => 'استرداد من الرصيد',
            default => $this->applies_to ?? 'غير معروف',
        };
    }
}
