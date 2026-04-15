<?php

use App\Http\Controllers\API\SaaS\TenantController;
use App\Http\Controllers\API\SaaS\ClinicOnboardingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SaaS Routes (Super Admin Only)
|--------------------------------------------------------------------------
*/

Route::prefix('saas')
    ->middleware(['auth:sanctum', 'super.admin'])
    ->group(function () {

        // Tenant management
        Route::get('/me', [TenantController::class, 'me']);
        Route::post('/switch-company', [TenantController::class, 'switch']);
        Route::post('/exit-company', [TenantController::class, 'exitCompany']);

        // Clinic onboarding (public - no auth required for register)
        Route::post('/register-clinic', [ClinicOnboardingController::class, 'register'])
            ->withoutMiddleware(['auth:sanctum', 'super.admin']);

        Route::get('/check-slug', [ClinicOnboardingController::class, 'checkSlug'])
            ->withoutMiddleware(['auth:sanctum', 'super.admin']);

        Route::get('/onboarding-progress', [ClinicOnboardingController::class, 'progress'])
            ->withoutMiddleware(['auth:sanctum', 'super.admin']);
    });
