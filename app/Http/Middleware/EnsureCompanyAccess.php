<?php

// namespace App\Http\Middleware;

// use Closure;
// use Illuminate\Http\Request;

// class EnsureCompanyAccess
// {
//     public function handle(Request $request, Closure $next)
//     {
//         $user = auth()->user();

//         if (!$user) {
//             return redirect()->route('login');
//         }

//         if ($user->isSuperAdmin()) {
//             return $next($request);
//         }

//         if (!$user->company_id) {
//             auth()->logout();
//             return redirect()->route('login')
//                 ->with('error', 'Your account is not associated with any clinic.');
//         }

//         $company = $user->company;

//         if ($company->isSuspended()) {
//             auth()->logout();
//             return redirect()->route('login')
//                 ->with('error', 'Your clinic account has been suspended.');
//         }

//         if ($company->isTrial() && $company->trialHasExpired()) {
//             return redirect()->route('subscription.expired');
//         }

//         return $next($request);
//     }
// }
