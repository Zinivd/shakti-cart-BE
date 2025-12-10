<?php

namespace App\Http\Controllers;

use App\Models\AuthUser;
use App\Helpers\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Exception;

class AuthController extends Controller
{
    // ----------------------------------------------------------------------
    // 🔒 Generate Encrypted Token
    // ----------------------------------------------------------------------
    private function createToken(AuthUser $user)
    {
        $payload = [
            'unique_id' => $user->unique_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'user_type' => $user->user_type,
            'timestamp' => now()->timestamp
        ];

        return Crypt::encryptString(json_encode($payload));
    }

    // ----------------------------------------------------------------------
    // 🧩 REGISTER
    // ----------------------------------------------------------------------
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:auth_users,email',
                'phone' => 'required|string|unique:auth_users,phone',
                'password' => 'required|min:6',
                'user_type' => 'nullable|string|in:customer,admin,vendor'
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
                'unique_id' => $user->unique_id,
                'user_type' => $user->user_type
            ]);

        } catch (Exception $e) {
            Log::error("Register Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Registration failed. Please try again later.'
            ], 500);
        }
    }

    // ----------------------------------------------------------------------
    // 🔐 LOGIN
    // ----------------------------------------------------------------------
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $user = AuthUser::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Create Token
            $token = $this->createToken($user);

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

        } catch (Exception $e) {
            Log::error("Login Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again later.'
            ], 500);
        }
    }

    // ----------------------------------------------------------------------
    // 🚪 LOGOUT
    // ----------------------------------------------------------------------
    public function logout(Request $request)
    {
        try {
            $request->validate(['email' => 'required|email']);

            $user = AuthUser::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $user->session_token = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (Exception $e) {
            Log::error("Logout Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Logout failed. Please try again later.'
            ], 500);
        }
    }
}
