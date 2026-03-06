<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\DentalRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DentalRecordController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = DentalRecord::query()
            ->where('company_id', $companyId)
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
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'procedure_id' => [
                'nullable',
                'integer',
                Rule::exists('procedures', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'tooth_number' => ['required', 'string', 'max:10'],
            'surface' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['planned', 'in_progress', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ]);

        $record = DentalRecord::create([
            'company_id' => $companyId,
            'customer_id' => $data['customer_id'],
            'appointment_id' => $data['appointment_id'] ?? null,
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
                'procedure:id,company_id,name,default_price',
            ]),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $record = DentalRecord::query()
            ->where('company_id', $companyId)
            ->with([
                'customer:id,name,email,company_id',
                'appointment:id,company_id,appointment_date,appointment_time,status',
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
        $companyId = $request->user()->company_id;

        $record = DentalRecord::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $data = $request->validate([
            'appointment_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'procedure_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('procedures', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
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
                'procedure:id,company_id,name,default_price',
            ]),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $record = DentalRecord::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $record->delete();

        return response()->json([
            'msg' => 'Dental record deleted',
            'status' => 200,
        ]);
    }
}
