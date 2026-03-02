<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Appointment;
use Illuminate\Http\Request;

class AppointmentActivityController extends Controller
{
    public function index(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        // ✅ تأكيد أن الموعد بتاع نفس الشركة (Tenant safe)
        Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $logs = ActivityLog::query()
            ->where('company_id', $companyId)
            ->where('subject_type', Appointment::class)
            ->where('subject_id', (int) $id)
            ->orderByDesc('id')
            ->get([
                'id',
                'company_id',
                'user_id',
                'action',
                'subject_type',
                'subject_id',
                'properties',
                'created_at',
            ]);

        return response()->json([
            'msg' => 'Appointment activity',
            'status' => 200,
            'data' => $logs,
        ]);
    }
}
