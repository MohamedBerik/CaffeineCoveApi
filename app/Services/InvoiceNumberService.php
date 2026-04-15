<?php

namespace App\Services;

use App\Models\ClinicSetting;
use App\Models\Invoice;
use App\Services\Tenant;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /**
     * Generate next invoice number for a company
     */
    public static function generate(?int $companyId = null): string
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            throw new \Exception('Company ID is required to generate invoice number');
        }

        return DB::transaction(function () use ($companyId) {
            // ✅ بدون where('company_id') - الـ Scope هيضيفه
            $settings = ClinicSetting::query()
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

            // ✅ Sync with latest existing invoice number
            $lastInvoice = Invoice::query()
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

    /**
     * Preview next invoice number without incrementing
     */
    public static function preview(?int $companyId = null): string
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            throw new \Exception('Company ID is required to preview invoice number');
        }

        // ✅ بدون where('company_id')
        $settings = ClinicSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'invoice_prefix' => 'INV',
                'invoice_start_number' => 1,
                'next_invoice_number' => 1,
            ]
        );

        $prefix = trim((string) ($settings->invoice_prefix ?: 'INV'));
        $startNumber = max(1, (int) ($settings->invoice_start_number ?: 1));
        $nextNumber = max($startNumber, (int) ($settings->next_invoice_number ?: $startNumber));

        // ✅ Check last invoice without lock
        $lastInvoice = Invoice::query()
            ->where('number', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->first();

        if ($lastInvoice && preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', (string) $lastInvoice->number, $matches)) {
            $lastUsedNumber = (int) $matches[1];
            if ($nextNumber <= $lastUsedNumber) {
                $nextNumber = $lastUsedNumber + 1;
            }
        }

        return sprintf('%s-%04d', $prefix, $nextNumber);
    }

    /**
     * Set custom invoice prefix for a company
     */
    public static function setPrefix(string $prefix, ?int $companyId = null): void
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            throw new \Exception('Company ID is required to set invoice prefix');
        }

        // ✅ بدون where('company_id')
        $settings = ClinicSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            ['invoice_prefix' => 'INV']
        );

        $settings->update(['invoice_prefix' => strtoupper(trim($prefix))]);
    }

    /**
     * Reset invoice counter for a company
     */
    public static function resetCounter(int $startNumber = 1, ?int $companyId = null): void
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            throw new \Exception('Company ID is required to reset invoice counter');
        }

        if ($startNumber < 1) {
            throw new \InvalidArgumentException('Start number must be >= 1');
        }

        // ✅ بدون where('company_id')
        $settings = ClinicSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            ['invoice_start_number' => 1, 'next_invoice_number' => 1]
        );

        $settings->update([
            'invoice_start_number' => $startNumber,
            'next_invoice_number' => $startNumber,
        ]);

        ActivityLogger::log(
            $companyId,
            auth()->user(),
            'invoice.counter_reset',
            ClinicSetting::class,
            $settings->id,
            ['new_start_number' => $startNumber]
        );
    }

    /**
     * Get current invoice settings
     */
    public static function getSettings(?int $companyId = null): array
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            throw new \Exception('Company ID is required to get invoice settings');
        }

        // ✅ بدون where('company_id')
        $settings = ClinicSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'invoice_prefix' => 'INV',
                'invoice_start_number' => 1,
                'next_invoice_number' => 1,
            ]
        );

        return [
            'prefix' => $settings->invoice_prefix ?? 'INV',
            'start_number' => (int) ($settings->invoice_start_number ?? 1),
            'next_number' => (int) ($settings->next_invoice_number ?? 1),
            'preview' => self::preview($companyId),
        ];
    }

    /**
     * Check if invoice number already exists
     */
    public static function exists(string $invoiceNumber, ?int $companyId = null): bool
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            return false;
        }

        return Invoice::query()
            ->where('number', $invoiceNumber)
            ->exists();
    }

    /**
     * Validate invoice number format
     */
    public static function validateFormat(string $invoiceNumber, ?int $companyId = null): bool
    {
        $settings = self::getSettings($companyId);
        $prefix = preg_quote($settings['prefix'], '/');

        return (bool) preg_match('/^' . $prefix . '-\d{4,}$/', $invoiceNumber);
    }
}
