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
        // ✅ Global API rate limit
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by(optional($request->user())->id ?: $request->ip());
        });

        // ✅ Stricter limit for authentication routes
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip());
        });

        // ✅ Tenant-specific rate limit
        RateLimiter::for('tenant', function (Request $request) {
            $companyId = Tenant::id() ?? $request->ip();
            return Limit::perMinute(120)
                ->by('tenant_' . $companyId);
        });

        // ✅ High-frequency endpoints (dashboard polling)
        RateLimiter::for('dashboard', function (Request $request) {
            return Limit::perMinute(30)
                ->by(optional($request->user())->id ?: $request->ip());
        });

        // ✅ Public endpoints (more restrictive)
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->ip());
        });

        // ✅ Report generation (resource intensive)
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(10)
                ->by(optional($request->user())->id ?: $request->ip());
        });

        // ✅ File uploads
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(30)
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
