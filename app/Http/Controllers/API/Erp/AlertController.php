<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;
use App\Services\Tenant;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * جلب الإشعارات المفلترة ديناميكياً حسب الفرع
     */
    public function index(Request $request)
    {
        $query = SystemAlert::query();
        $user = $request->user();

        $query->where(function ($q) use ($user) {
            $q->whereNull('user_id')
                ->orWhere('user_id', $user->id);
        });

        // 🚀 [التعديل الذهبي]: التصفية الصريحة حسب الفرع لمنع كاش وعشوائية الميدياوير أثناء التنقل الفوري
        // نقرأ أولاً من الـ query parameter، ثم الهيدر، ثم الـ container كخط دفاع أخير
        $branchId = $request->query('branch_id')
            ?: $request->header('X-Branch-ID')
            ?: (app()->has('tenant_branch_id') ? app('tenant_branch_id') : null);

        // إذا كان الفرع محدداً وليس "all"، نطبق الفلترة فوراً
        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        // Filter حسب الحالة
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
        $query = SystemAlert::query()
            ->whereNull('acknowledged_at')
            ->where(function ($q) use ($request) {
                $user = $request->user();

                $q->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            });

        // 🚀 تأمين عداد الإشعارات أيضاً عند التبديل اللحظي للفروع
        $branchId = $request->query('branch_id')
            ?: $request->header('X-Branch-ID')
            ?: (app()->has('tenant_branch_id') ? app('tenant_branch_id') : null);

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $count = $query->count();

        return response()->json(['count' => $count]);
    }

    /**
     * تحديد الإشعار كمقروء
     */
    public function acknowledge($id)
    {
        $user = request()->user();

        $alert = SystemAlert::query()
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->findOrFail($id);

        $alert->update([
            'acknowledged_at' => now()
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function acknowledgeMany(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['message' => 'No IDs provided'], 400);
        }

        \App\Models\SystemAlert::where('user_id', auth()->id())
            ->whereIn('id', $ids)
            ->update(['acknowledged_at' => now()]);

        return response()->json(['message' => 'Acknowledged']);
    }

    /**
     * تحديد كل الإشعارات كمقروءة
     */
    public function markAllRead(Request $request)
    {
        $user = $request->user();

        $query = SystemAlert::query()
            ->whereNull('acknowledged_at')
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            });

        // تأمين الـ mark all read لتعمل على مستوى الفرع النشط فقط إذا مرر بالطلب
        $branchId = $request->query('branch_id') ?: $request->header('X-Branch-ID');
        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $query->update([
            'acknowledged_at' => now()
        ]);

        return response()->json(['status' => 'ok']);
    }
}
