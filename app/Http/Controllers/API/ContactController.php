<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
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
            'phone' => 'nullable|string|max:50',
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

        // 1. حفظ الرسالة في قاعدة البيانات أولاً (ضمان عدم فقدانها)
        $contact = ContactMessage::create([
            'name'    => $request->name,
            'email'   => $request->email,
            'phone' => $request->phone,
            'subject' => $request->subject,
            'message' => $request->message,
        ]);

        // 2. محاولة إرسال البريد الإلكتروني (إذا فشل لا نوقف المستخدم)
        try {
            $platformEmail = \App\Models\PlatformSetting::get('general');
            $settings = $platformEmail ? json_decode($platformEmail, true) : [];
            $toEmail = $settings['platform_email'] ?? config('mail.from.address');

            Mail::raw(
                "From: {$request->name} ({$request->email})\n\nPhone: {$request->phone}Subject: {$request->subject}\n\n{$request->message}",
                function ($message) use ($request, $toEmail) {
                    $message->to($toEmail)
                        ->subject("Contact Us: {$request->subject}")
                        ->replyTo($request->email, $request->name);
                }
            );
        } catch (\Exception $e) {
            \Log::error('Contact email failed: ' . $e->getMessage());
            // لا نمنع المستخدم، فالرسالة محفوظة
        }

        return response()->json([
            'msg'    => 'Your message has been sent successfully. We will get back to you soon.',
            'status' => 200
        ]);
    }
}
