<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PatientProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        // استخراج الملخص المالي من الخدمة
        $financial = $this['financial_summary'] ?? [];
        $creditBalance = $financial['credit_balance'] ?? [];
        $invoicesSummary = $financial['invoices'] ?? [];
        $ledger = $financial['ledger'] ?? [];

        return [
            // --- بيانات المريض (كما هي) ---
            'patient' => [
                'id'            => $this['patient']->id,
                'name'          => $this['patient']->name,
                'email'         => $this['patient']->email,
                'phone'         => $this['patient']->phone,
                'patient_code'  => $this['patient']->patient_code,
                'date_of_birth' => $this['patient']->date_of_birth,
                'gender'        => $this['patient']->gender,
                'address'       => $this['patient']->address,
                'notes'         => $this['patient']->notes,
                'status'        => $this['patient']->status,
                'created_at'    => $this['patient']->created_at,
                'updated_at'    => $this['patient']->updated_at,
            ],

            // --- القوائم (كما هي) ---
            'procedures'      => $this['procedures'],
            'appointments'    => $this['appointments'],
            'dental_records'  => $this['dental_records'],
            'treatment_plans' => $this['treatment_plans'],
            'invoices'        => $this['invoices'],

            // --- المقاييس المالية مُستخرَجة بشكل مسطح (متوافقة مع الواجهة القديمة) ---
            'customer_credit_balance'  => (float) ($creditBalance['net_credit'] ?? 0),
            'invoices_total'           => (float) ($invoicesSummary['total'] ?? 0),
            'invoices_direct_paid'     => (float) ($invoicesSummary['direct_paid'] ?? 0),
            'invoices_credit_applied'  => (float) ($invoicesSummary['credit_applied'] ?? 0),
            'invoices_paid'            => (float) ($invoicesSummary['paid'] ?? 0),
            'invoices_remaining'       => (float) ($invoicesSummary['remaining'] ?? 0),

            // --- أرصدة وتفاصيل إضافية (متوافقة أيضًا) ---
            'credit_balance' => [
                'credit_issued' => (float) ($creditBalance['credit_issued'] ?? 0),
                'credit_used'   => (float) ($creditBalance['credit_used'] ?? 0),
                'net_credit'    => (float) ($creditBalance['net_credit'] ?? 0),
            ],

            'statement_summary' => [
                'opening_balance' => (float) ($ledger['opening_balance'] ?? 0),
                'total_debit'     => (float) ($ledger['total_debit'] ?? 0),
                'total_credit'    => (float) ($ledger['total_credit'] ?? 0),
                'closing_balance' => (float) ($ledger['closing_balance'] ?? 0),
            ],

            // --- الكائن الكامل للمستقبل (اختياري) ---
            'financial_summary' => $this['financial_summary'],
        ];
    }
}
