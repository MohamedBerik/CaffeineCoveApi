<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function acknowledge($id, Request $request)
    {
        $companyId = $request->user()->company_id;

        $alert = SystemAlert::query()
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->firstOrFail();

        $alert->update([
            'acknowledged_at' => now(),
        ]);

        return response()->json([
            'message' => 'Alert acknowledged'
        ]);
    }
}
