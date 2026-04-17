<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCompanyUser
{
    // app/Http/Middleware/EnsureCompanyUser.php

    public function handle(Request $request, Closure $next)
    {
        // ✅ علّق كل حاجة مؤقتًا
        return $next($request);
    }
}
