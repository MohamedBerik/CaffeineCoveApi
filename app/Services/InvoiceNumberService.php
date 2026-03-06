<?php

namespace App\Services;

use App\Models\ClinicSetting;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public static function generate(int $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $settings = ClinicSetting::where('company_id', $companyId)
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

            if ((int) $settings->next_invoice_number < (int) $settings->invoice_start_number) {
                $settings->next_invoice_number = (int) $settings->invoice_start_number;
                $settings->save();
            }

            $currentNumber = (int) $settings->next_invoice_number;
            $prefix = trim((string) ($settings->invoice_prefix ?: 'INV'));

            $invoiceNumber = sprintf('%s-%04d', $prefix, $currentNumber);

            $settings->next_invoice_number = $currentNumber + 1;
            $settings->save();

            return $invoiceNumber;
        });
    }
}
