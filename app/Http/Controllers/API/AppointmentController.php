<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = Appointment::where('company_id', $companyId)
            ->with(['patient:id,name,email,company_id'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time');

        // optional search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('doctor_name', 'like', "%$search%")
                    ->orWhere('notes', 'like', "%$search%");
            })->orWhereHas('patient', function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        $perPage = (int)($request->get('per_page', 20));
        $data = $query->paginate($perPage);

        return response()->json([
            'msg' => 'Appointments list',
            'status' => 200,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'patient_id' => ['required', 'integer'],
            'doctor_name' => ['nullable', 'string', 'max:190'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
            'notes' => ['nullable', 'string'],
        ]);

        $appointment = Appointment::create([
            ...$data,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'msg' => 'Appointment created',
            'status' => 201,
            'data' => $appointment->load('patient:id,name,email,company_id'),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::where('company_id', $companyId)
            ->with('patient:id,name,email,company_id')
            ->findOrFail($id);

        return response()->json([
            'msg' => 'Appointment details',
            'status' => 200,
            'data' => $appointment,
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::where('company_id', $companyId)->findOrFail($id);

        $data = $request->validate([
            'patient_id' => ['sometimes', 'integer'],
            'doctor_name' => ['nullable', 'string', 'max:190'],
            'appointment_date' => ['sometimes', 'date'],
            'appointment_time' => ['sometimes', 'date_format:H:i'],
            'status' => ['sometimes', Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
            'notes' => ['nullable', 'string'],
        ]);

        $appointment->update($data);

        return response()->json([
            'msg' => 'Appointment updated',
            'status' => 200,
            'data' => $appointment->fresh()->load('patient:id,name,email,company_id'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::where('company_id', $companyId)->findOrFail($id);
        $appointment->delete();

        return response()->json([
            'msg' => 'Appointment deleted',
            'status' => 200,
            'data' => null,
        ]);
    }
}
