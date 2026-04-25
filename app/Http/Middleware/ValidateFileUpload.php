<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateFileUpload
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->hasFile('file') || $request->hasFile('product_image') || $request->hasFile('logo')) {
            $maxSize = 10240; // 10MB
            $allowedMimes = ['jpeg', 'png', 'jpg', 'gif', 'pdf'];

            foreach ($request->allFiles() as $file) {
                // ✅ التحقق من الحجم
                if ($file->getSize() > $maxSize * 1024) {
                    return response()->json([
                        'message' => 'File size exceeds maximum allowed size.',
                        'max_size' => $maxSize . 'KB',
                    ], 422);
                }

                // ✅ التحقق من الامتداد
                $extension = strtolower($file->getClientOriginalExtension());
                if (!in_array($extension, $allowedMimes)) {
                    return response()->json([
                        'message' => 'File type not allowed.',
                        'allowed_types' => $allowedMimes,
                    ], 422);
                }

                // ✅ التحقق من MIME Type الحقيقي
                $mimeType = $file->getMimeType();
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    return response()->json([
                        'message' => 'Invalid file type detected.',
                    ], 422);
                }
            }
        }

        return $next($request);
    }
}
