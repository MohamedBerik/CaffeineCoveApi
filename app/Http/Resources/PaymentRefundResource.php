<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentRefundResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'payment_id' => $this->payment_id,

            'amount' => (float) $this->amount,
            'amount_formatted' => number_format($this->amount, 2) . ' EGP',

            'applies_to' => $this->applies_to,
            'applies_to_label' => $this->getAppliesToLabel(),

            'is_for_invoice' => $this->isForInvoice(),
            'is_for_credit' => $this->isForCredit(),

            'created_by' => $this->created_by,

            'refunded_at' => $this->refunded_at ? $this->refunded_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            'payment' => $this->whenLoaded('payment', function () {
                return [
                    'id' => $this->payment->id,
                    'amount' => $this->payment->amount,
                    'method' => $this->payment->method,
                    'paid_at' => $this->payment->paid_at ? $this->payment->paid_at->toIso8601String() : null,
                ];
            }),

            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),

            'invoice' => $this->whenLoaded('payment.invoice', function () {
                return [
                    'id' => $this->payment->invoice->id,
                    'number' => $this->payment->invoice->number,
                ];
            }),

            'customer' => $this->whenLoaded('payment.invoice.customer', function () {
                return [
                    'id' => $this->payment->invoice->customer->id,
                    'name' => $this->payment->invoice->customer->name,
                ];
            }),
        ];
    }

    public function with($request): array
    {
        return ['status' => 'success'];
    }

    private function getAppliesToLabel(): string
    {
        switch ($this->applies_to) {
            case 'invoice':
                return 'استرداد من الفاتورة';
            case 'credit':
                return 'استرداد من الرصيد';
            default:
                return $this->applies_to ?? 'غير معروف';
        }
    }
}
