<?php

namespace App\Providers;

use App\Services\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureRoutePatterns();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            // ✅ SaaS routes
            Route::prefix('api/saas')
                ->middleware('api')
                ->namespace($this->namespace . '\SaaS')
                ->group(base_path('routes/saas.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // ✅ Global API rate limit (تم رفعه لـ 1000 لمنع الـ 429 أثناء الـ Debugging والعمل المكثف)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(1000)
                ->by(optional($request->user())->id ?: $request->ip());
        });

        // ✅ Stricter limit for authentication routes (نحافظ عليه لحماية اللوجن من التخمين)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(20) // تم رفعه قليلاً لـ 20 ليعطيك أريحية تجارب تسجيل الدخول المتتالية
                ->by($request->ip());
        });

        // ✅ Tenant-specific rate limit (تم رفعه لـ 1500 ليتحمل فروع العيادات وضغط الموظفين)
        RateLimiter::for('tenant', function (Request $request) {
            $companyId = Tenant::id() ?? $request->ip();
            return Limit::perMinute(1500)
                ->by('tenant_' . $companyId);
        });

        // ✅ High-frequency endpoints (dashboard polling)
        // إذا كنت تستخدم Polling بالفرونت إند، 30 طلب قد تنتهي سريعاً، يفضل رفعها لـ 300
        RateLimiter::for('dashboard', function (Request $request) {
            return Limit::perMinute(300)
                ->by(optional($request->user())->id ?: $request->ip());
        });

        // ✅ Public endpoints (more restrictive)
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(100)
                ->by($request->ip());
        });

        // ✅ Report generation (resource intensive)
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(50)
                ->by(optional($request->user())->id ?: $request->ip());
        });

        // ✅ File uploads
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(60)
                ->by(optional($request->user())->id ?: $request->ip());
        });
    }

    /**
     * Configure route pattern constraints
     */
    protected function configureRoutePatterns(): void
    {
        // ✅ ID pattern - must be numeric
        Route::pattern('id', '[0-9]+');

        // ✅ Slug pattern - alphanumeric with hyphens
        Route::pattern('slug', '[a-z0-9-]+');

        // ✅ Date pattern - YYYY-MM-DD
        Route::pattern('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');

        // ✅ UUID pattern
        Route::pattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
    }
}
