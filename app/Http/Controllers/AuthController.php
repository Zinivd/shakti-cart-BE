<?php

namespace App\Http\Controllers;

use App\Models\AuthUser;
use App\Helpers\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;

class AuthController extends Controller
{
    // 🧩 User Registration
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:auth_users',
            'phone' => 'required|string|unique:auth_users',
            'password' => 'required|min:6',
            'user_type' => 'nullable|string|in:customer,admin,vendor' // allowed types
        ]);

        $uniqueId = UserHelper::generateUniqueId();

        $user = AuthUser::create([
            'unique_id' => $uniqueId,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'user_type' => $request->user_type ?? 'customer',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'unique_id' => $uniqueId,
            'user_type' => $user->user_type
        ]);
    }

    // 🔐 User Login — generate token
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = AuthUser::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials']);
        }

        // Generate encrypted token
        $tokenData = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'unique_id' => $user->unique_id,
            'user_type' => $user->user_type,
            'timestamp' => now()->timestamp
        ];

        $token = Crypt::encryptString(json_encode($tokenData));

        $user->session_token = $token;
        $user->last_login_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user->only([
                'unique_id',
                'name',
                'email',
                'phone',
                'user_type',
                'last_login_at'
            ])
        ]);
    }

    // 🚪 Logout — expire token
    public function logout(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = AuthUser::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found']);
        }

        $user->session_token = null;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }
}
