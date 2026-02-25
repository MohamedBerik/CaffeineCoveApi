<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyAccountingInitializer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ClinicOnboardingController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'clinic_name' => ['required', 'string', 'max:190'],
            'email'       => ['required', 'email', 'max:190', 'unique:users,email'],
            'password'    => ['required', Password::min(8)],
        ]);

        return DB::transaction(function () use ($data) {

            // 1) unique slug
            $slugBase = Str::slug($data['clinic_name']);
            $slug = $slugBase;
            $i = 1;
            while (Company::where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . $i;
                $i++;
            }

            // 2) create company
            $company = Company::create([
                'name'         => $data['clinic_name'],
                'slug'         => $slug,
                'status'       => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'branding'     => [
                    'app_name'      => $data['clinic_name'],
                    'logo'          => null,
                    'primary_color' => '#0ea5e9',
                ],
            ]);
            CompanyAccountingInitializer::init($company->id);

            // 3) create admin user
            $user = User::create([
                'name'           => 'Clinic Admin',
                'email'          => $data['email'],
                'password'       => bcrypt($data['password']),
                'company_id'     => $company->id,
                'role'           => 'admin',
                'status'         => 1,
                'is_super_admin' => 0,
            ]);

            // 4) issue token (auto-login)
            $token = $user->createToken('clinic_admin')->plainTextToken;

            return response()->json([
                'msg' => 'Clinic registered successfully',
                'token' => $token,
                'tenant' => [
                    'company_id' => $company->id,
                    'slug' => $company->slug,
                ],
                'clinic' => $company->only(['id', 'name', 'slug', 'status', 'trial_ends_at', 'branding']),
                'user' => $user->only(['id', 'name', 'email', 'company_id', 'role', 'is_super_admin']),
            ], 201);
        });
    }
}
