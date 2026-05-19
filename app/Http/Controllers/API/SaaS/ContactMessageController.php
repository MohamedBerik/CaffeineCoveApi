<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $messages = ContactMessage::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'msg' => 'Contact messages list',
            'status' => 200,
            'data' => $messages
        ]);
    }

    public function show($id)
    {
        $message = ContactMessage::findOrFail($id);
        return response()->json([
            'msg' => 'Message details',
            'status' => 200,
            'data' => $message
        ]);
    }

    public function markAsRead($id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->update(['read_at' => now()]);

        return response()->json([
            'msg' => 'Message marked as read',
            'status' => 200,
        ]);
    }

    public function destroy($id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->delete();

        return response()->json([
            'msg' => 'Message deleted successfully',
            'status' => 200,
        ]);
    }
}
