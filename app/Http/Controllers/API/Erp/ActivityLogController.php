<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $limit = (int) $request->get('limit', 6);

        $logs = ActivityLog::where('company_id', $companyId)
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json($logs);
    }
}
