<?php

namespace App\Services;

use App\Models\ClinicSetting;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public static function generate(int $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $settings = ClinicSetting::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->firstOrCreate(
                    ['company_id' => $companyId],
                    [
                        'clinic_name' => 'My Clinic',
                        'currency' => 'USD',
                        'timezone' => 'UTC',
                        'invoice_prefix' => 'INV',
                        'invoice_start_number' => 1,
                        'next_invoice_number' => 1,
                        'language' => 'en',
                    ]
                );

            $prefix = trim((string) ($settings->invoice_prefix ?: 'INV'));
            $startNumber = max(1, (int) ($settings->invoice_start_number ?: 1));
            $nextNumber = max($startNumber, (int) ($settings->next_invoice_number ?: $startNumber));

            // ✅ safeguard: sync with latest existing invoice number for same company/prefix
            $lastInvoice = Invoice::query()
                ->where('company_id', $companyId)
                ->where('number', 'like', $prefix . '-%')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($lastInvoice && preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', (string) $lastInvoice->number, $matches)) {
                $lastUsedNumber = (int) $matches[1];
                if ($nextNumber <= $lastUsedNumber) {
                    $nextNumber = $lastUsedNumber + 1;
                }
            }

            $invoiceNumber = sprintf('%s-%04d', $prefix, $nextNumber);

            $settings->next_invoice_number = $nextNumber + 1;
            $settings->save();

            return $invoiceNumber;
        });
    }
}
