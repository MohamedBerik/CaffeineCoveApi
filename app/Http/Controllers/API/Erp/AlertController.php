<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    // ✅ دالة جلب الإشعارات
    public function index()
    {
        $alerts = SystemAlert::where('company_id', auth()->user()->company_id)
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'message' => $alert->message,
                    'priority' => $alert->priority,
                    'type' => $alert->type,
                    'time' => $alert->triggered_at,
                    'read' => $alert->acknowledged_at !== null,
                ];
            });

        return response()->json($alerts);
    }

    // ✅ دالة جلب عدد الإشعارات غير المقروءة
    public function unreadCount()
    {
        $count = SystemAlert::where('company_id', auth()->user()->company_id)
            ->where(function ($query) {
                $query->where('user_id', auth()->id())
                    ->orWhereNull('user_id');
            })
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
}
