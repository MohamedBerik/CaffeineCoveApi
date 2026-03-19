<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\DentalRecord;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PatientTimelineController extends Controller
{
    public function index(Request $request, $customerId)
    {
        $companyId = $request->user()->company_id;

        $customer = Customer::query()
            ->where('company_id', $companyId)
            ->findOrFail($customerId);

        $appointments = Appointment::query()
            ->where('company_id', $companyId)
            ->where('patient_id', $customer->id)
            ->with([
                'doctor:id,name,company_id',
                'invoice:id,appointment_id,treatment_plan_id',
            ])
            ->get()
            ->map(function ($row) {
                return [
                    'type' => 'appointment',
                    'event_at' => $this->combineDateTime($row->appointment_date, $row->appointment_time),
                    'created_at' => optional($row->created_at)?->toISOString(),
                    'data' => [
                        'id' => $row->id,
                        'patient_id' => $row->patient_id,
                        'status' => $row->status,
                        'appointment_type' => $row->appointment_type,
                        'appointment_date' => $row->appointment_date,
                        'appointment_time' => substr((string) $row->appointment_time, 0, 5),
                        'doctor_id' => $row->doctor_id,
                        'doctor_name' => $row->doctor->name ?? $row->doctor_name,
                        'notes' => $row->notes,
                        'clinical_notes' => $row->clinical_notes,
                        'diagnosis' => $row->diagnosis,
                        'next_step' => $row->next_step,
                        'invoice_id' => $row->invoice?->id,
                        'treatment_plan_id' => $row->invoice?->treatment_plan_id,
                    ],
                ];
            });

        $dentalRecords = DentalRecord::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->with([
                'procedure:id,name,default_price',
                'treatmentPlanItem:id,treatment_plan_id,appointment_id,procedure_id,tooth_number,surface,status,planned_sessions,completed_sessions',
            ])
            ->get()
            ->map(function ($row) {
                return [
                    'type' => 'dental_record',
                    'event_at' => optional($row->created_at)?->toISOString(),
                    'created_at' => optional($row->created_at)?->toISOString(),
                    'data' => [
                        'id' => $row->id,
                        'appointment_id' => $row->appointment_id,
                        'procedure_id' => $row->procedure_id,
                        'procedure_name' => $row->procedure->name ?? null,
                        'tooth_number' => $row->tooth_number,
                        'surface' => $row->surface,
                        'status' => $row->status,
                        'notes' => $row->notes,

                        'treatment_plan_item' => $row->treatmentPlanItem ? [
                            'id' => $row->treatmentPlanItem->id,
                            'treatment_plan_id' => $row->treatmentPlanItem->treatment_plan_id,
                            'appointment_id' => $row->treatmentPlanItem->appointment_id,
                            'procedure_id' => $row->treatmentPlanItem->procedure_id,
                            'tooth_number' => $row->treatmentPlanItem->tooth_number,
                            'surface' => $row->treatmentPlanItem->surface,
                            'status' => $row->treatmentPlanItem->status,
                            'planned_sessions' => (int) ($row->treatmentPlanItem->planned_sessions ?? 1),
                            'completed_sessions' => (int) ($row->treatmentPlanItem->completed_sessions ?? 0),
                            'remaining_sessions' => max(
                                (int) ($row->treatmentPlanItem->planned_sessions ?? 1) -
                                    (int) ($row->treatmentPlanItem->completed_sessions ?? 0),
                                0
                            ),
                        ] : null,
                    ],
                ];
            });

        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->get()
            ->map(function ($row) {
                return [
                    'type' => 'invoice',
                    'event_at' => $row->issued_at
                        ? \Illuminate\Support\Carbon::parse($row->issued_at)->toISOString()
                        : optional($row->created_at)?->toISOString(),
                    'created_at' => optional($row->created_at)?->toISOString(),
                    'data' => [
                        'id' => $row->id,
                        'number' => $row->number,
                        'appointment_id' => $row->appointment_id,
                        'treatment_plan_id' => $row->treatment_plan_id,
                        'total' => (float) $row->total,
                        'status' => $row->status,
                        'issued_at' => $row->issued_at,
                    ],
                ];
            });

        $payments = Payment::query()
            ->where('company_id', $companyId)
            ->whereIn('invoice_id', function ($q) use ($companyId, $customer) {
                $q->select('id')
                    ->from('invoices')
                    ->where('company_id', $companyId)
                    ->where('customer_id', $customer->id);
            })
            ->get()
            ->map(function ($row) {
                return [
                    'type' => 'payment',
                    'event_at' => $row->paid_at
                        ? \Illuminate\Support\Carbon::parse($row->paid_at)->toISOString()
                        : optional($row->created_at)?->toISOString(),
                    'created_at' => optional($row->created_at)?->toISOString(),
                    'data' => [
                        'id' => $row->id,
                        'invoice_id' => $row->invoice_id,
                        'amount' => (float) $row->amount,
                        'applied_amount' => (float) $row->applied_amount,
                        'credit_amount' => (float) $row->credit_amount,
                        'method' => $row->method,
                        'paid_at' => $row->paid_at,
                    ],
                ];
            });

        $refunds = DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->where('payment_refunds.company_id', $companyId)
            ->where('invoices.company_id', $companyId)
            ->where('invoices.customer_id', $customer->id)
            ->select([
                'payment_refunds.id',
                'payment_refunds.payment_id',
                'payment_refunds.applies_to',
                'payment_refunds.amount',
                'payment_refunds.refunded_at',
                'payment_refunds.created_at',
                'payments.invoice_id',
            ])
            ->get()
            ->map(function ($row) {
                return [
                    'type' => 'refund',
                    'event_at' => $row->refunded_at
                        ? \Illuminate\Support\Carbon::parse($row->refunded_at)->toISOString()
                        : ($row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at)->toISOString() : null),
                    'created_at' => $row->created_at
                        ? \Illuminate\Support\Carbon::parse($row->created_at)->toISOString()
                        : null,
                    'data' => [
                        'id' => $row->id,
                        'payment_id' => $row->payment_id,
                        'invoice_id' => $row->invoice_id,
                        'applies_to' => $row->applies_to,
                        'amount' => (float) $row->amount,
                        'refunded_at' => $row->refunded_at,
                    ],
                ];
            });

        $timeline = (new Collection)
            ->concat($appointments)
            ->concat($dentalRecords)
            ->concat($invoices)
            ->concat($payments)
            ->concat($refunds)
            ->sortByDesc(function ($item) {
                return $item['event_at'] ?? $item['created_at'] ?? now()->toISOString();
            })
            ->values();

        return response()->json([
            'msg' => 'Patient timeline',
            'status' => 200,
            'data' => [
                'patient' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone ?? null,
                    'patient_code' => $customer->patient_code ?? null,
                ],
                'timeline' => $timeline,
            ],
        ]);
    }

    private function combineDateTime($date, $time): ?string
    {
        if (!$date) {
            return null;
        }

        $dateOnly = \Illuminate\Support\Carbon::parse($date)->toDateString();
        $timeOnly = $time ? substr((string) $time, 0, 5) : '00:00';

        return \Illuminate\Support\Carbon::parse($dateOnly . ' ' . $timeOnly)->toISOString();
    }
}
