<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Services\Tenant;
use Illuminate\Support\Facades\Log;

class ActivityLogController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ActivityLog::class, 'activityLog');
    }

    public function index(Request $request)
    {
        $limit = (int) $request->get('limit', 20); // ✅ 20 أفضل من 6

        try {
            // ✅ Eager load user relationship (Performance)
            $query = ActivityLog::with('user:id,name,email');

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
                    'user_id' => $log->user_id,
                    'user_name' => $log->user?->name,
                    'user_email' => $log->user?->email, // ✅ اختياري
                    'subject_type' => $log->subject_type,
                    'subject_id' => $log->subject_id,
                    'properties' => $log->properties,
                    'created_at' => $log->created_at?->toISOString(), // ✅ String format
                ]),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            // ✅ Log for internal debugging
            Log::error('ActivityLog Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'tenant_id' => Tenant::id(),
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            // ✅ Safe response for production
            return response()->json([
                'message' => 'Failed to fetch activity logs. Please try again later.',
            ], 500);
        }
    }
}
