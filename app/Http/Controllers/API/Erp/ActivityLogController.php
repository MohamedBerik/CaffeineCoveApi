<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Services\Tenant; // ✅ استخدام Tenant

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $limit = (int) $request->get('limit', 6);

        // ✅ استخدام Global Scope - إزالة where('company_id')
        $logs = ActivityLog::query()
            ->when(
                $request->subject_type,
                fn($q) => $q->where('subject_type', $request->subject_type)
            )
            ->latest()
            ->paginate($limit);

        return response()->json([
            'data' => $logs->getCollection()->map(fn($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'properties' => $log->properties,
                'created_at' => $log->created_at,
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ]
        ]);
    }
}
