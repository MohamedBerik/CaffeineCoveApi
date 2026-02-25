<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->company_id) {
            return response()->json([
                'msg' => 'Tenant not resolved for this user',
            ], 422);
        }

        $company = Company::query()
            ->where('id', $user->company_id)
            ->firstOrFail();

        $trialEndsAt = $company->trial_ends_at ? $company->trial_ends_at->copy() : null;

        $now = now();
        $trialDaysRemaining = null;
        $isTrialExpired = false;

        if ($trialEndsAt) {
            // لو انتهى: remaining = 0
            $trialDaysRemaining = max(0, $now->startOfDay()->diffInDays($trialEndsAt->startOfDay(), false));
            $isTrialExpired = $trialEndsAt->lt($now);
        }

        // Access decision (ممكن تبسطها في v1)
        $access = true;

        // لو عايز تقفل الوصول بعد انتهاء الـ trial (اختياري الآن)
        // if ($company->status === 'trial' && $isTrialExpired) $access = false;

        return response()->json([
            'tenant' => [
                'company_id' => $company->id,
                'name'       => $company->name,
                'slug'       => $company->slug,
                'status'     => $company->status,
                'trial_ends_at' => $company->trial_ends_at,
                'trial_days_remaining' => $trialDaysRemaining,
                'is_trial_expired' => $isTrialExpired,
                'branding'   => $company->branding,
                'access'     => $access,
            ],
            'user' => $user->only(['id', 'name', 'email', 'company_id', 'role', 'is_super_admin']),
        ]);
    }
}
