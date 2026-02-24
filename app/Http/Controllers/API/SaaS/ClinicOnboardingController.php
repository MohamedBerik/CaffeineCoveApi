
<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
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

            $slugBase = Str::slug($data['clinic_name']);
            $slug = $slugBase;
            $i = 1;
            while (Company::where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . $i++;
            }

            $company = Company::create([
                'name' => $data['clinic_name'],
                'slug' => $slug,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'branding' => json_encode([
                    'app_name' => $data['clinic_name'],
                    'primary_color' => '#0ea5e9',
                ]),
            ]);

            $user = User::create([
                'name' => 'Clinic Admin',
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'company_id' => $company->id,
                'role' => 'admin',
                'status' => 1,
                'is_super_admin' => 0,
            ]);

            return response()->json([
                'msg' => 'Clinic registered successfully',
                'clinic' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'status' => $company->status,
                    'trial_ends_at' => $company->trial_ends_at,
                ],
                'admin_user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'company_id' => $user->company_id,
                ],
            ], 201);
        });
    }
}
