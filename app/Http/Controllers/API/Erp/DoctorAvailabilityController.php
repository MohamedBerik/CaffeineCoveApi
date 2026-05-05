<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Services\DoctorAvailabilityService;
use App\Services\Tenant;
use Illuminate\Http\Request;
use App\Models\Doctor;

class DoctorAvailabilityController extends Controller
{

    public function show(Request $request, $doctorId, DoctorAvailabilityService $service)
    {
        $doctor = Doctor::findOrFail($doctorId);
        $this->authorize('view', $doctor);          // ✅ يطبق DoctorPolicy

        $companyId = Tenant::id();

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'include_booked' => ['nullable', 'boolean'],
        ]);

        $includeBooked = array_key_exists('include_booked', $data)
            ? (bool) $data['include_booked']
            : true;

        $availability = $service->getAvailability(
            $companyId,
            (int) $doctorId,
            $data['date'],
            $includeBooked
        );

        return response()->json([
            'msg' => 'Doctor availability',
            'status' => 200,
            'data' => $availability,
        ]);
    }
}
