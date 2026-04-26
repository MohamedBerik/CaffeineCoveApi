<?php

// namespace App\Http\Middleware;

// use Closure;
// use Illuminate\Http\Request;

// class SecurityHeaders
// {
//     public function handle(Request $request, Closure $next)
//     {
//         $response = $next($request);

//         // ✅ منع MIME-type sniffing
//         $response->headers->set('X-Content-Type-Options', 'nosniff');

//         // ✅ منع Clickjacking
//         $response->headers->set('X-Frame-Options', 'DENY');

//         // ✅ منع XSS
//         $response->headers->set('X-XSS-Protection', '1; mode=block');

//         // ✅ Referrer Policy
//         $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

//         // ✅ Permissions Policy
//         $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

//         // ✅ Content Security Policy
//         $response->headers->set(
//             'Content-Security-Policy',
//             "default-src 'self'; " .
//                 "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com; " .
//                 "style-src 'self' 'unsafe-inline'; " .
//                 "img-src 'self' data: https:; " .
//                 "font-src 'self'; " .
//                 "frame-src 'self' https://js.stripe.com https://accept.paymob.com; " .
//                 "connect-src 'self' https:;"
//         );

//         // ✅ HSTS (للإنتاج فقط)
//         if (app()->environment('production')) {
//             $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
//         }

//         return $response;
//     }
// }
