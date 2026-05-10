<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientProfileResource;
use App\Models\Customer;
use App\Services\Erp\PatientProfileService;
use App\Services\Tenant;
use Illuminate\Http\Request;

class PatientProfileController extends Controller
{
    public function __construct(
        protected PatientProfileService $patientProfileService
    ) {}

    public function show(Request $request, int $customerId)
    {
        $companyId = Tenant::id();

        $customer = Customer::query()
            ->where('company_id', $companyId)
            ->findOrFail($customerId);

        $this->authorize('view', $customer);

        $profile = $this->patientProfileService
            ->build($customer);

        return response()->json([
            'msg' => 'Patient profile',
            'status' => 200,
            'data' => PatientProfileResource::make($profile),
        ]);
    }
}
