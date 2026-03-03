<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Services\DoctorAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentAvailabilityController extends Controller
{
    /**
     * GET /api/erp/appointments/available-slots?doctor_id=1&date=YYYY-MM-DD&include_booked=1
     * Default: include_booked=false (يرجع المتاح فقط)
     */
    public function index(Request $request, DoctorAvailabilityService $service)
    {
        $companyId = (int) $request->user()->company_id;

        $data = $request->validate([
            'doctor_id' => [
                'required',
                'integer',
                Rule::exists('doctors', 'id')->where(fn($q) => $q->where('company_id', $companyId)->where('is_active', true)),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'include_booked' => ['nullable', 'boolean'],
        ]);

        $includeBooked = (bool) ($data['include_booked'] ?? false);

        $availability = $service->getAvailability(
            $companyId,
            (int) $data['doctor_id'],
            $data['date'],
            $includeBooked
        );

        return response()->json([
            'msg' => 'Available slots',
            'status' => 200,
            'data' => $availability,
        ]);
    }
}
