<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

// Broadcast::channel('company.{companyId}', function ($user, $companyId) {
//     return (int) $user->company_id === (int) $companyId;
// });

Broadcast::channel('company.{companyId}', function ($user, $companyId) {
    return true; // ✅ للتجربة فقط
});

// Broadcast::channel('alerts', function ($user) {
//     return $user != null;
// });

// Broadcast::channel('alerts', function ($user) {
//     return $user->role === 'admin';
// });
