<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;
use App\Services\Tenant;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    // public function __construct()
    // {
    //     $this->authorizeResource(SystemAlert::class, 'alert');
    // }
    /**
     * جلب الإشعارات المفروزة بالفروع
     */
    public function index(Request $request)
    {
        // تحديد الفرع الحالي المستهدف (إما من الـ Header أو من الـ Query Parameter)
        $branchId = $request->header('X-Branch-ID') ?? $request->query('branch_id');

        $query = SystemAlert::query();

        // 🛡️ تطبيق عزل الفروع الذكي
        if ($branchId && $branchId !== 'all') {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id'); // الإشعارات العامة الموجهة لكل الشركة
            });
        }

        // Filters
        if ($request->filter === 'unread') {
            $query->whereNull('acknowledged_at');
        }

        if ($request->filter === 'high') {
            $query->where('priority', 'high');
        }

        // Pagination
        $alerts = $query->latest('triggered_at')->paginate(20);

        return response()->json([
            'data' => collect($alerts->items())->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'message' => $alert->message,
                    'priority' => $alert->priority,
                    'type' => $alert->type,
                    'code' => $alert->code,
                    'time' => $alert->triggered_at?->toISOString(),
                    'read' => $alert->acknowledged_at !== null,
                ];
            }),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'has_more' => $alerts->hasMorePages(),
            ]
        ]);
    }

    /**
     * جلب عدد الإشعارات غير المقروءة لفرع محدد
     */
    public function unreadCount(Request $request)
    {
        $branchId = $request->header('X-Branch-ID') ?? $request->query('branch_id');

        $query = SystemAlert::query()->whereNull('acknowledged_at');

        // 🛡️ تطبيق عزل العداد للفروع
        if ($branchId && $branchId !== 'all') {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        return response()->json(['count' => $query->count()]);
    }

    /**
     * تحديد الإشعار كمقروء
     */
    public function acknowledge($id)
    {
        $alert = SystemAlert::query()->findOrFail($id);
        $alert->update(['acknowledged_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * تحديد إشعارات الفرع الحالي فقط كمقروءة
     */
    public function markAllRead(Request $request)
    {
        $branchId = $request->header('X-Branch-ID') ?? $request->query('branch_id');

        $query = SystemAlert::query()->whereNull('acknowledged_at');

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $query->update(['acknowledged_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
