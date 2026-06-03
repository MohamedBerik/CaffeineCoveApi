<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray($request)
    {
        // الحصول على القيم المالية بأمان
        $totalPurchases = method_exists($this->resource, 'getTotalPurchasesAttribute')
            ? (float) $this->total_purchases
            : 0;

        $totalPaid = method_exists($this->resource, 'getTotalPaidAttribute')
            ? (float) $this->total_paid
            : 0;

        $balance = $totalPurchases - $totalPaid;

        // تحديد الحالة بناءً على الرصيد
        $status = 'settled';
        if ($balance > 0) {
            $status = 'owed';
        } elseif ($balance < 0) {
            $status = 'overpaid';
        }

        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id'  => $this->branch_id, // ✅ أضفه إن وجد في الجدول
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,

            // Additional Fields
            'address' => $this->address,
            'contact_person' => $this->contact_person,
            'notes' => $this->notes,

            // Financial Summary (محسوبة بأمان)
            'total_purchases' => $totalPurchases,
            'total_purchases_formatted' => number_format($totalPurchases, 2) . ' EGP',
            'total_paid' => $totalPaid,
            'total_paid_formatted' => number_format($totalPaid, 2) . ' EGP',
            'balance' => $balance,
            'balance_formatted' => number_format($balance, 2) . ' EGP',

            // Status
            'status' => $status,
            'status_label' => $this->getStatusLabelByStatus($status),
            'status_color' => $this->getStatusColorByStatus($status),
            'has_balance' => $balance > 0,
            'has_overpayment' => $balance < 0,

            // Counts (فقط عند تحميل العلاقات)
            'purchases_count' => $this->whenLoaded('purchaseOrders', fn() => $this->purchaseOrders->count(), 0),
            'payments_count' => $this->whenLoaded('payments', fn() => $this->payments->count(), 0),

            // Dates
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (only when loaded)
            'purchase_orders' => PurchaseOrderResource::collection($this->whenLoaded('purchaseOrders')),
            'payments' => SupplierPaymentResource::collection($this->whenLoaded('payments')),
        ];
    }

    public function with($request): array
    {
        return [
            'status' => 'success',
        ];
    }

    private function getStatusLabelByStatus(string $status): string
    {
        return match ($status) {
            'owed' => 'مستحق عليه',
            'overpaid' => 'له رصيد',
            default => 'متوازن',
        };
    }

    private function getStatusColorByStatus(string $status): string
    {
        return match ($status) {
            'owed' => 'red',
            'overpaid' => 'orange',
            default => 'green',
        };
    }
}
