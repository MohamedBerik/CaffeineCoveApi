<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Services\Tenant;

class ActivityLogController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ActivityLog::class, 'activityLog');
    }

    public function index(Request $request)
    {
        $limit = (int) $request->get('limit', 6);

        try {

            // ✅ الاعتماد على Global Scope فقط
            $query = ActivityLog::query();

            $logs = $query
                ->when($request->subject_type, fn($q) => $q->where('subject_type', $request->subject_type))
                ->when($request->action, fn($q) => $q->where('action', $request->action))
                ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
                ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
                ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
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
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'tenant_id' => Tenant::id(),
            ], 500);
        }
    }
}
