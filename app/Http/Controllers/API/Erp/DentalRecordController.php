<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\DentalRecord;
use App\Models\TreatmentPlanItem;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class DentalRecordController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(DentalRecord::class, 'dentalRecord', [
            'except' => ['toTreatmentPlanItem', 'update']
        ]);
    }

    public function index(Request $request)
    {
        $query = DentalRecord::query()
            ->with([
                'customer:id,name,email,company_id',
                'appointment:id,company_id,appointment_date,appointment_time,status',
                'procedure:id,company_id,name,default_price',
            ])
            ->orderByDesc('id');

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($appointmentId = $request->query('appointment_id')) {
            $query->where('appointment_id', $appointmentId);
        }

        if ($toothNumber = $request->query('tooth_number')) {
            $query->where('tooth_number', $toothNumber);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'msg' => 'Dental records list',
            'status' => 200,
            'data' => $query->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Tenant::id();

        $data = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id'),
            ],
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('appointments', 'id'),
            ],
            'doctor_id' => [
                'nullable',
                'integer',
                Rule::exists('doctors', 'id')->where('is_active', true),
            ],
            'procedure_id' => [
                'nullable',
                'integer',
                Rule::exists('procedures', 'id'),
            ],
            'tooth_number' => ['required', 'string', 'max:10'],
            'surface' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['planned', 'in_progress', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ]);

        $record = DentalRecord::create([
            'company_id' => $companyId,
            'branch_id' => Tenant::branchId() ?? $request->user()->branch_id,
            'customer_id' => $data['customer_id'],
            'appointment_id' => $data['appointment_id'] ?? null,
            'doctor_id' => $data['doctor_id'] ?? null,
            'procedure_id' => $data['procedure_id'] ?? null,
            'tooth_number' => $data['tooth_number'],
            'surface' => $data['surface'] ?? null,
            'status' => $data['status'] ?? 'planned',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'msg' => 'Dental record created',
            'status' => 201,
            'data' => $record->load([
                'customer:id,name,email,company_id',
                'appointment:id,company_id,appointment_date,appointment_time,status',
                'doctor:id,name,company_id',
                'procedure:id,company_id,name,default_price',
            ]),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $record = DentalRecord::query()
            ->with([
                'customer:id,name,email,company_id',
                'appointment:id,company_id,appointment_date,appointment_time,status',
                'doctor:id,name,company_id',
                'procedure:id,company_id,name,default_price',
            ])
            ->findOrFail($id);

        return response()->json([
            'msg' => 'Dental record details',
            'status' => 200,
            'data' => $record,
        ]);
    }

    public function update(Request $request, $id)
    {
        $record = DentalRecord::query()->findOrFail($id);

        $data = $request->validate([
            'appointment_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('appointments', 'id'),
            ],
            'procedure_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('procedures', 'id'),
            ],
            'tooth_number' => ['sometimes', 'required', 'string', 'max:10'],
            'surface' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'nullable', Rule::in(['planned', 'in_progress', 'completed', 'cancelled'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $record->update($data);

        return response()->json([
            'msg' => 'Dental record updated',
            'status' => 200,
            'data' => $record->fresh()->load([
                'customer:id,name,email,company_id',
                'appointment:id,company_id,appointment_date,appointment_time,status',
                'doctor:id,name,company_id',
                'procedure:id,company_id,name,default_price',
            ]),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $record = DentalRecord::query()->findOrFail($id);
        $record->delete();

        return response()->json([
            'msg' => 'Dental record deleted',
            'status' => 200,
        ]);
    }

    public function toTreatmentPlanItem(Request $request, $id)
    {
        $companyId = Tenant::id();

        $record = DentalRecord::query()
            ->with('procedure')
            ->findOrFail($id);

        $this->authorize('view', $record);

        if ($record->treatment_plan_item_id) {
            return response()->json([
                'msg' => 'Dental record already converted to treatment plan item',
                'status' => 409,
                'data' => [
                    'treatment_plan_item_id' => $record->treatment_plan_item_id,
                ],
            ], 409);
        }

        $data = $request->validate([
            'treatment_plan_id' => [
                'required',
                'integer',
                Rule::exists('treatment_plans', 'id'),
            ],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $plan = \App\Models\TreatmentPlan::query()
            ->findOrFail($data['treatment_plan_id']);

        if ((int) $plan->customer_id !== (int) $record->customer_id) {
            return response()->json([
                'msg' => 'Treatment plan does not belong to this customer',
                'status' => 422,
                'errors' => [
                    'treatment_plan_id' => ['Treatment plan customer_id mismatch.'],
                ],
            ], 422);
        }

        if (!$record->procedure_id || !$record->procedure) {
            return response()->json([
                'msg' => 'Dental record must have a procedure before conversion',
                'status' => 422,
                'errors' => [
                    'procedure_id' => ['Dental record procedure_id is required.'],
                ],
            ], 422);
        }

        $duplicate = TreatmentPlanItem::query()
            ->where('treatment_plan_id', $plan->id)
            ->where('procedure_id', $record->procedure_id)
            ->where('tooth_number', $record->tooth_number)
            ->where(function ($q) use ($record) {
                if (is_null($record->surface)) {
                    $q->whereNull('surface');
                } else {
                    $q->where('surface', $record->surface);
                }
            })
            ->first();

        if ($duplicate) {
            $record->update([
                'treatment_plan_item_id' => $duplicate->id,
            ]);

            return response()->json([
                'msg' => 'Dental record already converted to treatment plan item',
                'status' => 409,
                'data' => [
                    'treatment_plan_item_id' => $duplicate->id,
                ],
            ], 409);
        }

        return DB::transaction(function () use ($companyId, $record, $plan, $data) {
            $price = $data['price'] ?? (float) ($record->procedure->default_price ?? 0);

            $item = TreatmentPlanItem::create([
                'company_id'         => $companyId,
                'treatment_plan_id'  => $plan->id,
                'procedure_id'       => $record->procedure_id,
                'procedure'          => $record->procedure->name,
                'tooth_number'       => $record->tooth_number,
                'surface'            => $record->surface,
                'notes'              => $record->notes,
                'price'              => $price,
                'planned_sessions'   => 1,
                'completed_sessions' => 0,
                'status'             => 'planned',
            ]);

            $record->update([
                'treatment_plan_item_id' => $item->id,
            ]);

            $sum = TreatmentPlanItem::query()
                ->where('treatment_plan_id', $plan->id)
                ->sum('price');

            $plan->update([
                'total_cost' => $sum,
            ]);

            return response()->json([
                'msg' => 'Dental record converted to treatment plan item',
                'status' => 201,
                'data' => $item->fresh()->load('procedureRef'),
            ], 201);
        });
    }
}
