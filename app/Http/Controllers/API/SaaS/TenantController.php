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

        if (!$user) {
            return response()->json([
                'msg' => 'Unauthenticated (missing/invalid token)',
            ], 401);
        }

        $company = Company::where('id', $user->company_id)->first();

        if (!$company) {
            return response()->json([
                'msg' => 'Company not found for this user',
                'debug' => [
                    'user_id' => $user->id,
                    'user_company_id' => $user->company_id,
                ],
            ], 422);
        }

        return response()->json([
            'tenant' => [
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => $company->status,
                'trial_ends_at' => $company->trial_ends_at,
                'branding' => $company->branding,
            ],
            'user' => $user->only(['id', 'name', 'email', 'company_id', 'role', 'is_super_admin']),
        ]);
    }
}
