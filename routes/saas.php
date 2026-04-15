<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\SaaS\ClinicOnboardingController;
use App\Http\Controllers\API\SaaS\TenantController;
/*
|--------------------------------------------------------------------------
| SaaS routes (tenant onboarding)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'company.user'])->prefix('saas')->group(function () {
    Route::get('/me', [TenantController::class, 'me']);
    Route::post('/register-clinic', [ClinicOnboardingController::class, 'register']);
});
