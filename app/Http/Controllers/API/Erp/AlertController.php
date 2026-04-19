<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;
use App\Services\Tenant;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(SystemAlert::class, 'alert');
    }

    /**
     * جلب الإشعارات
     */
    public function index(Request $request)
    {
        $query = SystemAlert::query();

        // Filter
        if ($request->filter === 'unread') {
            $query->whereNull('acknowledged_at');
        }

        if ($request->filter === 'high') {
            $query->where('priority', 'high');
        }

        // Pagination
        $alerts = $query
            ->latest('triggered_at')
            ->paginate(20);

        return response()->json([
            'data' => collect($alerts->items())->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'message' => $alert->message,
                    'priority' => $alert->priority,
                    'type' => $alert->type,
                    'code' => $alert->code,
                    'time' => $alert->triggered_at,
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
     * جلب عدد الإشعارات غير المقروءة
     */
    public function unreadCount()
    {
        $count = SystemAlert::query()
            ->whereNull('acknowledged_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * تحديد الإشعار كمقروء
     */
    public function acknowledge($id)
    {
        $alert = SystemAlert::query()->findOrFail($id);

        $alert->update([
            'acknowledged_at' => now()
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * تحديد كل الإشعارات كمقروءة
     */
    public function markAllRead()
    {
        SystemAlert::query()
            ->whereNull('acknowledged_at')
            ->update([
                'acknowledged_at' => now()
            ]);

        return response()->json(['status' => 'ok']);
    }
}
