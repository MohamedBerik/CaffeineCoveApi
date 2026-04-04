<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\SystemAlert;

class AlertController extends Controller
{

    public function ack($id)
    {
        SystemAlert::where('id', $id)->update([
            'acknowledged_at' => now()
        ]);

        return response()->json(['status' => true]);
    }
}
