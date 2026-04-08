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
