<?php

namespace App\Http\Controllers;

use App\Models\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class userInfoController extends Controller
{
    // ---------------------------------------------------
    // 🔐 VALIDATE TOKEN (returns user or JsonResponse)
    // ---------------------------------------------------
    private function validateToken(Request $request)
    {
        try {
            $token = $request->header('Authorization');

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization token missing'
                ], 401);
            }

            $token = str_replace('Bearer ', '', $token);

            $decoded = json_decode(Crypt::decryptString($token), true);

            if (!isset($decoded['email'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token structure'
                ], 401);
            }

            $user = AuthUser::where('email', $decoded['email'])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token user not found'
                ], 404);
            }

            if ($user->session_token !== $token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expired or invalid token'
                ], 401);
            }

            return $user;

        } catch (Exception $e) {

            Log::error("Token validation error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Token decryption failed'
            ], 401);
        }
    }

    // ---------------------------------------------------
    // 👤 GET USER INFO (email)
    // ---------------------------------------------------
    public function userInfo(Request $request)
    {
        try {
            $email = $request->query('email');

            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email parameter is required'
                ], 400);
            }

            $user = $this->validateToken($request);
            if ($user instanceof \Illuminate\Http\JsonResponse)
                return $user;

            if ($email !== $user->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email mismatch with token'
                ], 403);
            }

            $format = fn($time) => $time
                ? Carbon::parse($time)->setTimezone('Asia/Kolkata')->toDateTimeString()
                : null;

            return response()->json([
                'success' => true,
                'message' => 'User details fetched successfully',
                'data' => [
                    'unique_id' => $user->unique_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'user_type' => $user->user_type,
                    'last_login_at' => $format($user->last_login_at)
                ]
            ]);

        } catch (Exception $e) {

            Log::error("Fetch user info error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Unable to fetch user info'
            ], 500);
        }
    }

    // ---------------------------------------------------
    // ✏ UPDATE PROFILE (ONLY name allowed)
    // ---------------------------------------------------
    public function updateUserInfo(Request $request)
    {
        try {
            $user = $this->validateToken($request);
            if ($user instanceof \Illuminate\Http\JsonResponse)
                return $user;

            $request->validate([
                'name' => 'required|string|max:255'
            ]);

            $user->update(['name' => $request->name]);

            return response()->json([
                'success' => true,
                'message' => 'User info updated successfully',
                'data' => $user
            ]);

        } catch (Exception $e) {

            Log::error("Update user info error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Failed to update user info'
            ], 500);
        }
    }

    // ---------------------------------------------------
    // 🗑 DELETE USER (ADMIN ONLY)
    // ---------------------------------------------------
    public function deleteUser(Request $request)
    {
        try {
            $admin = $this->validateToken($request);
            if ($admin instanceof \Illuminate\Http\JsonResponse)
                return $admin;

            if ($admin->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admins can delete users'
                ], 403);
            }

            $request->validate([
                'unique_id' => 'required|string|exists:auth_users,unique_id'
            ]);

            $user = AuthUser::where('unique_id', $request->unique_id)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            if ($admin->unique_id === $user->unique_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin cannot delete themselves'
                ], 400);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (Exception $e) {

            Log::error("Delete user error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Failed to delete user'
            ], 500);
        }
    }

    // ---------------------------------------------------
    // 📌 GET ALL USERS (only customers)
    // ---------------------------------------------------
    public function getAllUsers(Request $request)
    {
        try {
            $auth = $this->validateToken($request);
            if ($auth instanceof \Illuminate\Http\JsonResponse)
                return $auth;

            $users = AuthUser::where('user_type', 'customer')->get();

            return response()->json([
                'success' => true,
                'count' => $users->count(),
                'data' => $users
            ]);

        } catch (Exception $e) {

            Log::error("Get all users error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Failed to fetch users'
            ], 500);
        }
    }

    // ---------------------------------------------------
    // 📌 GET USER BY UNIQUE_ID
    // ---------------------------------------------------
    public function getUserById(Request $request)
    {
        try {
            // 🔐 Validate token
            $auth = $this->validateToken($request);
            if ($auth instanceof \Illuminate\Http\JsonResponse)
                return $auth;

            // 🔎 Read from query params
            $uniqueId = $request->query('unique_id');

            if (!$uniqueId) {
                return response()->json([
                    'success' => false,
                    'message' => 'unique_id parameter is required'
                ], 400);
            }

            // Validate manually (because it's a query param)
            $user = AuthUser::where('unique_id', $uniqueId)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $user
            ]);

        } catch (Exception $e) {

            Log::error("Get user by ID error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Failed to fetch user'
            ], 500);
        }
    }
}
