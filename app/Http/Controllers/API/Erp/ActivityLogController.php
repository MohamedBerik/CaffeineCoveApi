<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\Concerns\CompanyScope;
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
        $limit = (int) $request->get('limit', 20);
        $user = auth()->user();

        try {
            $query = ActivityLog::with('user:id,name,email');

            // ✅ لو Super Admin وعايز يشوف كل الشركات
            if ($user->is_super_admin) {
                // لو طلب كل الشركات (Global Mode) أو company_id محدد
                if ($request->has('all_companies') || $request->filled('company_id')) {
                    $query = ActivityLog::withoutGlobalScope(CompanyScope::class);

                    // فلترة اختيارية بـ company_id
                    if ($request->filled('company_id')) {
                        $query->where('company_id', $request->company_id);
                    }
                }
            }

            $logs = $query
                ->when($request->subject_type, fn($q) => $q->where('subject_type', $request->subject_type))
                ->when($request->action, fn($q) => $q->where('action', $request->action))
                ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
                ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
                ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
                ->when($request->search, function ($q) use ($request) {
                    $q->where(function ($sq) use ($request) {
                        $sq->where('action', 'like', "%{$request->search}%")
                            ->orWhere('subject_type', 'like', "%{$request->search}%")
                            ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$request->search}%"));
                    });
                })
                ->latest()
                ->paginate($limit);

            return response()->json([
                'data' => $logs->getCollection()->map(fn($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user?->name,
                    'user_email' => $log->user?->email,
                    'subject_type' => $log->subject_type,
                    'subject_id' => $log->subject_id,
                    'company_id' => $log->company_id, // ✅ أضف company_id
                    'properties' => $log->properties,
                    'created_at' => $log->created_at?->toISOString(),
                ]),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('ActivityLog Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'tenant_id' => Tenant::id(),
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch activity logs. Please try again later.',
            ], 500);
        }
    }
}
