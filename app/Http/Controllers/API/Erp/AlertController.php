<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;

class AlertController extends Controller
{

    public function acknowledge($id)
    {
        $alert = SystemAlert::findOrFail($id);

        $alert->update([
            'acknowledged_at' => now()
        ]);

        return response()->json(['status' => 'ok']);
    }
}
