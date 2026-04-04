<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $limit = (int) $request->get('limit', 6);

        $logs = ActivityLog::where('company_id', $companyId)
            ->when(
                $request->subject_type,
                fn($q) =>
                $q->where('subject_type', $request->subject_type)
            )
            ->latest()
            ->paginate(10);

        return response()->json(
            $logs->map(fn($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'properties' => $log->properties,
                'created_at' => $log->created_at,
            ])
        );
    }
}
