<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| هذا الملف متروك فارغ تقريبًا
| لأن الواجهة الأمامية React
| و Laravel يعمل كـ API فقط
|
*/

// Route افتراضي اختياري (للتأكد إن Laravel شغال)
Route::get('/', function () {
    return response()->json([
        'status' => 'Laravel API is running',
        'version' => app()->version(),
    ]);
});


// routes/web.php
// Route::get('/check-key', function () {
//     return [
//         'env' => env('APP_ENV'),
//         'app_key_env' => env('APP_KEY'),
//         'app_key_config' => config('app.key'),
//     ];
// });
