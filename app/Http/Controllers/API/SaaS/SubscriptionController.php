<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Company;
use App\Models\Plan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscription::with(['company', 'plan']);

        if ($search = $request->get('search')) {
            $query->whereHas('company', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->orWhereHas('plan', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $subscriptions = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'msg' => 'Subscriptions list',
            'status' => 200,
            'data' => $subscriptions->items(),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,pending,cancelled,expired,trial'],
            'payment_method' => ['nullable', 'string'],
            'transaction_id' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $subscription = Subscription::create($data);

        return response()->json([
            'msg' => 'Subscription created successfully',
            'status' => 201,
            'data' => $subscription,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $subscription = Subscription::findOrFail($id);

        $data = $request->validate([
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:active,pending,cancelled,expired,trial'],
            'payment_method' => ['nullable', 'string'],
            'transaction_id' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $subscription->update($data);

        return response()->json([
            'msg' => 'Subscription updated successfully',
            'status' => 200,
            'data' => $subscription,
        ]);
    }

    public function cancel($id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->update(['status' => 'cancelled']);

        return response()->json([
            'msg' => 'Subscription cancelled successfully',
            'status' => 200,
        ]);
    }

    public function renew($id)
    {
        $subscription = Subscription::findOrFail($id);

        // إغلاق أي اشتراكات نشطة قديمة لنفس الشركة
        Subscription::where('company_id', $subscription->company_id)
            ->where('status', 'active')
            ->update([
                'status' => 'expired',
            ]);

        $newSubscription = $subscription->replicate();

        $newSubscription->starts_at = now();

        if ($subscription->billing_cycle === 'yearly') {
            $newSubscription->ends_at = now()->addYear();
        } else {
            $newSubscription->ends_at = now()->addMonth();
        }

        $newSubscription->status = 'active';

        $newSubscription->save();

        // تحديث حالة الشركة
        Company::where('id', $subscription->company_id)
            ->update([
                'status' => Company::STATUS_ACTIVE,
                'trial_ends_at' => null,
            ]);

        return response()->json([
            'msg' => 'Subscription renewed successfully',
            'status' => 200,
            'data' => $newSubscription,
        ]);
    }
}
