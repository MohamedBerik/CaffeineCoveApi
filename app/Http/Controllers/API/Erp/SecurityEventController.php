<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\SecurityEventResource;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityEventController extends Controller
{
    public function __construct()
    {
        // فقط الأدمن والسوبر أدمن يمكنهم رؤية السجل الأمني
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (!$user || (!$user->is_super_admin && $user->role !== 'admin')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $limit = (int) $request->get('limit', 20);

        $query = SecurityEvent::query()
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->email, fn($q) => $q->where('email', 'like', "%{$request->email}%"))
            ->when($request->ip, fn($q) => $q->where('ip', $request->ip))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest();

        $events = $query->paginate($limit);

        return response()->json([
            'data' => SecurityEventResource::collection($events),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page'    => $events->lastPage(),
                'total'        => $events->total(),
                'per_page'     => $events->perPage(),
            ],
        ]);
    }

    public function show($id)
    {
        $event = SecurityEvent::findOrFail($id);
        return response()->json(['data' => $event]);
    }
}
