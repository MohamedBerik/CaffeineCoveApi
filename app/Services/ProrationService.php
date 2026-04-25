<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Plan;
use Carbon\Carbon;

class ProrationService
{
    /**
     * حساب المبلغ المستحق عند الترقية أو التخفيض
     */
    public function calculate(Subscription $subscription, Plan $newPlan): array
    {
        $oldPlan = $subscription->plan;

        // ✅ لو نفس الخطة، مفيش تغيير
        if ($oldPlan->id === $newPlan->id) {
            return [
                'type' => 'same_plan',
                'amount' => 0,
                'description' => 'Same plan - no charge',
            ];
        }

        // ✅ حساب الأيام المتبقية في الاشتراك الحالي
        $endsAt = Carbon::parse($subscription->ends_at);
        $remainingDays = now()->diffInDays($endsAt);
        $totalDays = Carbon::parse($subscription->starts_at)->diffInDays($endsAt);

        // ✅ المبلغ المدفوع لليوم الواحد
        $dailyRate = $totalDays > 0 ? $oldPlan->price / $totalDays : 0;

        // ✅ المبلغ المتبقي (غير المستخدم)
        $unusedAmount = $dailyRate * $remainingDays;

        // ✅ المبلغ اليومي للخطة الجديدة
        $newDailyRate = $newPlan->price / 30; // شهري

        // ✅ تكلفة الخطة الجديدة للفترة المتبقية
        $newPlanCost = $newDailyRate * $remainingDays;

        // ✅ الفرق (موجب = مدين، سالب = دائن)
        $prorationAmount = $newPlanCost - $unusedAmount;

        return [
            'type' => $prorationAmount >= 0 ? 'charge' : 'credit',
            'amount' => round(abs($prorationAmount), 2),
            'remaining_days' => $remainingDays,
            'unused_amount' => round($unusedAmount, 2),
            'new_plan_cost' => round($newPlanCost, 2),
            'description' => $prorationAmount >= 0
                ? "Charge for upgrading to {$newPlan->name}"
                : "Credit for downgrading to {$newPlan->name}",
        ];
    }
}
