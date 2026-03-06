<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\ClinicSetting;
use Illuminate\Http\Request;

class ClinicSettingController extends Controller
{
    public function show(Request $request)
    {
        $companyId = $request->user()->company_id;

        $settings = ClinicSetting::firstOrCreate(
            ['company_id' => $companyId],
            [
                'clinic_name' => 'My Clinic',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'invoice_prefix' => 'INV',
                'invoice_start_number' => 1,
                'next_invoice_number' => 1,
                'language' => 'en',
            ]
        );

        return response()->json([
            'msg' => 'Clinic settings',
            'status' => 200,
            'data' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $companyId = $request->user()->company_id;

        $settings = ClinicSetting::firstOrCreate(
            ['company_id' => $companyId],
            [
                'clinic_name' => 'My Clinic',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'invoice_prefix' => 'INV',
                'invoice_start_number' => 1,
                'next_invoice_number' => 1,
                'language' => 'en',
            ]
        );

        $data = $request->validate([
            'clinic_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string'],
            'invoice_prefix' => ['sometimes', 'string', 'max:10'],
            'invoice_start_number' => ['sometimes', 'integer', 'min:1'],
            'language' => ['sometimes', 'string', 'max:10'],
        ]);

        $settings->update($data);

        return response()->json([
            'msg' => 'Clinic settings updated',
            'status' => 200,
            'data' => $settings->fresh(),
        ]);
    }
}
