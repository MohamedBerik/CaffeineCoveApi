<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    // ✅ دالة جلب الإشعارات
    public function index(Request $request)
    {
        $query = SystemAlert::where('company_id', auth()->user()->company_id);

        // ✅ Filter
        if ($request->filter === 'unread') {
            $query->whereNull('acknowledged_at');
        }

        if ($request->filter === 'high') {
            $query->where('priority', 'high');
        }

        // ✅ Pagination (IMPORTANT)
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

    // ✅ دالة جلب عدد الإشعارات غير المقروءة
    public function unreadCount()
    {
        $count = SystemAlert::where('company_id', auth()->user()->company_id)
            // ->where(function ($query) {
            //     $query->where('user_id', auth()->id())
            //         ->orWhereNull('user_id');
            // })
            ->whereNull('acknowledged_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    // ✅ دالة تحديد كمقروء (موجودة بالفعل)
    public function acknowledge($id)
    {
        $alert = SystemAlert::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $alert->update([
            'acknowledged_at' => now()
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function markAllRead()
    {
        SystemAlert::where('company_id', auth()->user()->company_id)
            ->whereNull('acknowledged_at')
            ->update([
                'acknowledged_at' => now()
            ]);

        return response()->json(['status' => 'ok']);
    }
}
