<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyAccountingInitializer;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ClinicOnboardingController extends Controller
{
    /**
     * Register a new clinic (SaaS Onboarding)
     * This endpoint is public - creates company and first admin user
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'clinic_name' => ['required', 'string', 'max:190'],
            'email'       => ['required', 'email', 'max:190', 'unique:users,email'],
            'password'    => ['required', Password::min(8)],
        ]);

        return DB::transaction(function () use ($data) {

            // ✅ Run in Super Admin context to bypass company scope
            return Tenant::asSuperAdmin(function () use ($data) {

                // 1) Generate unique slug
                $slugBase = Str::slug($data['clinic_name']);
                $slug = $slugBase;
                $i = 1;
                while (Company::where('slug', $slug)->exists()) {
                    $slug = $slugBase . '-' . $i;
                    $i++;
                }

                // 2) Create company
                $company = Company::create([
                    'name'          => $data['clinic_name'],
                    'slug'          => $slug,
                    'status'        => Company::STATUS_TRIAL,
                    'trial_ends_at' => now()->addDays(14),
                    'branding'      => [
                        'app_name'      => $data['clinic_name'],
                        'logo'          => null,
                        'primary_color' => '#0ea5e9',
                    ],
                ]);

                // 3) Initialize accounting
                if (class_exists(CompanyAccountingInitializer::class)) {
                    CompanyAccountingInitializer::init($company->id);
                }

                // 4) Create admin user
                $user = User::create([
                    'name'           => 'Clinic Admin',
                    'email'          => $data['email'],
                    'password'       => bcrypt($data['password']),
                    'company_id'     => $company->id,
                    'role'           => 'admin',
                    'status'         => 1,
                    'is_super_admin' => false,
                ]);

                // 5) Issue token (auto-login)
                $token = $user->createToken('clinic_admin')->plainTextToken;

                // 6) Set tenant context for the new user
                Tenant::setId($company->id);
                Tenant::setIsSuperAdmin(false);

                return response()->json([
                    'msg'   => 'Clinic registered successfully',
                    'token' => $token,
                    'tenant' => [
                        'company_id' => $company->id,
                        'slug'       => $company->slug,
                    ],
                    'clinic' => $company->only(['id', 'name', 'slug', 'status', 'trial_ends_at', 'branding']),
                    'user'   => $user->only(['id', 'name', 'email', 'company_id', 'role', 'is_super_admin']),
                ], 201);
            });
        });
    }

    /**
     * Check if clinic slug is available
     */
    public function checkSlug(Request $request)
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:190'],
        ]);

        $slug = Str::slug($request->slug);

        $exists = Tenant::asSuperAdmin(function () use ($slug) {
            return Company::where('slug', $slug)->exists();
        });

        return response()->json([
            'slug' => $slug,
            'available' => !$exists,
        ]);
    }

    /**
     * Get onboarding progress (for resuming incomplete registration)
     */
    public function progress(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = Tenant::asSuperAdmin(function () use ($request) {
            return User::where('email', $request->email)->first();
        });

        if (!$user) {
            return response()->json([
                'status' => 'not_started',
            ]);
        }

        $company = $user->company;

        return response()->json([
            'status' => 'completed',
            'company' => $company?->only(['id', 'name', 'slug', 'status', 'trial_ends_at']),
        ]);
    }
}
