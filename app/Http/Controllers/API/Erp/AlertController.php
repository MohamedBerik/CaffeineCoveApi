<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;
use App\Services\Tenant;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * استخراج branchId من الطلب بشكل موحد
     */
    protected function resolveBranchId(Request $request): mixed
    {
        return $request->query('branch_id')
            ?: $request->header('X-Branch-ID')
            ?: (app()->has('tenant_branch_id') ? app('tenant_branch_id') : null);
    }

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

        $branchId = $this->resolveBranchId($request);

        if ($branchId && $branchId !== 'all') {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        if ($request->filter === 'unread') {
            $query->whereNull('acknowledged_at');
        }

        if ($request->filter === 'high') {
            $query->whereIn('priority', [
                SystemAlert::PRIORITY_HIGH,
                SystemAlert::PRIORITY_CRITICAL,
            ]);
        }

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

        $branchId = $this->resolveBranchId($request);

        if ($branchId && $branchId !== 'all') {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        $count = $query->count();

        return response()->json(['count' => $count]);
    }

    /**
     * تحديد إشعار واحد كمقروء (مع فحص الفرع)
     */
    public function acknowledge($id)
    {
        $user = request()->user();

        $query = SystemAlert::query()
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            });

        $branchId = $this->resolveBranchId(request());

        if ($branchId && $branchId !== 'all') {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        $alert = $query->findOrFail($id);

        $alert->update([
            'acknowledged_at' => now()
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * تحديد عدة إشعارات كمقروءة (مع عزل الفرع والشركة)
     */
    public function acknowledgeMany(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['message' => 'No IDs provided'], 400);
        }

        $query = SystemAlert::query()
            ->where('company_id', Tenant::id())
            ->whereIn('id', $ids)
            ->where(function ($q) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', auth()->id());
            });

        $branchId = $this->resolveBranchId($request);

        if ($branchId && $branchId !== 'all') {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        $query->update([
            'acknowledged_at' => now()
        ]);

        return response()->json(['message' => 'Acknowledged']);
    }

    /**
     * تحديد كل الإشعارات كمقروءة (مع عزل الفرع)
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

        $branchId = $this->resolveBranchId($request);

        if ($branchId && $branchId !== 'all') {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        $query->update([
            'acknowledged_at' => now()
        ]);

        return response()->json(['status' => 'ok']);
    }
}
