<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        // مثال: آخر 6 سجلات
        $logs = ActivityLog::latest()->limit($request->get('limit', 6))->get();
        return response()->json($logs);
    }
}
