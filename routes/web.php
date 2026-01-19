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

// لو هتجرب من المتصفح
Route::get('/check-env', function () {
    return [
        'app_key' => env('APP_KEY'),
        'db_host' => env('DB_HOST'),
        'db_database' => env('DB_DATABASE'),
        'db_user' => env('DB_USERNAME')
    ];
});
