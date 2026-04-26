<?php

namespace App\Http\Middleware;

use Closure;

class Cors
{
    public function handle($request, Closure $next)
    {
        // ✅ التعامل مع OPTIONS Preflight
        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $request->header('Origin'))
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-Tenant-Id')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        /** @var \Illuminate\Http\Response $response */
        $response = $next($request);

        // ✅ إضافة headers للردود العادية
        $response->headers->set('Access-Control-Allow-Origin', $request->header('Origin'));
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-Tenant-Id');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}


// return [

// 'paths' => ['api/*', 'admin/*', 'sanctum/csrf-cookie', 'broadcasting/auth', 'login', 'logout', 'register'],

// 'allowed_methods' => ['*'],

// 'allowed_origins' => [
// 'https://caffeine-cove-cafe.vercel.app',
// 'https://caffeinecoveapi-production-a107.up.railway.app',
// 'http://localhost:3000',
// 'http://localhost:5173',
// ],

// 'allowed_origins_patterns' => [],

// 'allowed_headers' => ['*'],

// 'exposed_headers' => [],

// 'max_age' => 0,

// 'supports_credentials' => true,

// ];
