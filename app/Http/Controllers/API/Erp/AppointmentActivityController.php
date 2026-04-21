<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Services\Tenant;
use Illuminate\Http\Request;

class AppointmentActivityController extends Controller
{
    public function index(Request $request, $id)
    {
        // ✅ تأكيد أن الموعد بتاع نفس الشركة
        $appointment = Appointment::query()->findOrFail($id);

        // ✅ Authorization check
        $this->authorize('view', $appointment);

        // ✅ Eager load user relationship
        $logs = ActivityLog::with('user:id,name')
            ->where('subject_type', Appointment::class)
            ->where('subject_id', (int) $id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'msg' => 'Appointment activity',
            'status' => 200,
            'data' => $logs->map(fn($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'properties' => $log->properties,
                'created_at' => $log->created_at?->toISOString(),
            ]),
        ]);
    }
}
