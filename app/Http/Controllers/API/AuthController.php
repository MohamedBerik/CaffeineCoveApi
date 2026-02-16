<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|confirmed',
            'company_id' => 'required|exists:companies,id',
        ]);

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => bcrypt($request->password),
            'company_id' => $request->company_id,
        ]);

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token
        ], 201);
    }

    // تسجيل دخول
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // حماية إضافية: لا يسمح بتسجيل الدخول لو المستخدم غير مرتبط بشركة
        if (!$user->company_id) {
            return response()->json([
                'message' => 'User is not assigned to any company'
            ], 403);
        }

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token
        ]);
    }
}





//Old Code
// namespace App\Http\Controllers\API;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Hash;
// use App\Models\User;

// class AuthController extends Controller
// {
//     public function register(Request $request)
//     {
//         $request->validate([
//             'name' => 'required|string|max:255',
//             'email' => 'required|email|unique:users,email',
//             'password' => 'required|confirmed',
//         ]);

//         $user = User::create([
//             'name' => $request->name,
//             'email' => $request->email,
//             'password' => bcrypt($request->password),
//         ]);

//         $token = $user->createToken('API Token')->plainTextToken;

//         return response()->json([
//             'user' => $user,
//             'token' => $token
//         ], 201);
//     }

//     // تسجيل دخول
//     public function login(Request $request)
//     {
//         $request->validate([
//             'email' => 'required|email',
//             'password' => 'required|string',
//         ]);

//         $user = User::where('email', $request->email)->first();

//         if (!$user || !Hash::check($request->password, $user->password)) {
//             return response()->json(['message' => 'Invalid credentials'], 401);
//         }

//         $token = $user->createToken('API Token')->plainTextToken;

//         return response()->json([
//             'user' => $user,
//             'token' => $token
//         ]);
//     }
// }
