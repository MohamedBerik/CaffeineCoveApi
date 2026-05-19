<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function send(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name'    => 'required|string|min:3|max:255',
            'email'   => 'required|email|max:255',
            'subject' => 'required|string|min:3|max:255',
            'message' => 'required|string|min:10|max:5000',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'msg'    => 'Validation required',
                'status' => 422,
                'errors' => $validate->errors()
            ], 422);
        }

        // جلب إيميل المنصة من الإعدادات
        $platformEmail = \App\Models\PlatformSetting::get('general');
        $settings = $platformEmail ? json_decode($platformEmail, true) : [];
        $toEmail = $settings['platform_email'] ?? config('mail.from.address');

        // إرسال البريد
        try {
            Mail::raw(
                "From: {$request->name} ({$request->email})\n\nSubject: {$request->subject}\n\n{$request->message}",
                function ($message) use ($request, $toEmail) {
                    $message->to($toEmail)
                        ->subject("Contact Us: {$request->subject}")
                        ->replyTo($request->email, $request->name);
                }
            );

            return response()->json([
                'msg'    => 'Your message has been sent successfully. We will get back to you soon.',
                'status' => 200
            ]);
        } catch (\Exception $e) {
            \Log::error('Contact form email failed: ' . $e->getMessage());
            return response()->json([
                'msg'    => 'Failed to send your message. Please try again later.',
                'status' => 500
            ], 500);
        }
    }
}
