<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\UserAlertPreference;
use Illuminate\Http\Request;

class UserAlertPreferenceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $preferences = UserAlertPreference::query()
                ->where('user_id', $request->user()->id)
                ->get();

            return response()->json($preferences);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
    public function update(Request $request)
    {
        $request->validate([
            'preferences' => 'required|array',
        ]);

        $user = $request->user();

        foreach ($request->preferences as $code => $enabled) {
            UserAlertPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'alert_code' => $code,
                ],
                [
                    'enabled' => (bool) $enabled,
                ]
            );
        }

        return response()->json([
            'message' => 'Preferences updated'
        ]);
    }
}
