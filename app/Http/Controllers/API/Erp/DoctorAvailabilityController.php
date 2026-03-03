<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Services\DoctorAvailabilityService;
use Illuminate\Http\Request;

class DoctorAvailabilityController extends Controller
{
    /**
     * GET /api/erp/doctors/{doctorId}/availability?date=YYYY-MM-DD&include_booked=1
     * Default: include_booked=true (يرجع كل اليوم مع available true/false)
     */
    public function show(Request $request, $doctorId, DoctorAvailabilityService $service)
    {
        $companyId = (int) $request->user()->company_id;

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
