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
    // 🔐 Validate token and return user
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

            // decrypt
            $decoded = json_decode(Crypt::decryptString($token), true);

            if (!isset($decoded['email'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token format'
                ], 401);
            }

            // find user
            $user = AuthUser::where('email', $decoded['email'])->first();

            if (!$user || $user->session_token !== $token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            return $user;

        } catch (Exception $e) {
            Log::error("Token validation failed: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Token decryption failed'
            ], 401);
        }
    }

    // 🔓 GET USER INFO
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

            // validate token
            $user = $this->validateToken($request);

            if ($user instanceof \Illuminate\Http\JsonResponse)
                return $user;

            if ($user->email !== $email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email mismatch with token user'
                ], 403);
            }

            // timestamp formatting
            $formatTime = fn($value) =>
                $value ? Carbon::parse($value)->setTimezone('Asia/Kolkata')->toDateTimeString() : null;

            return response()->json([
                'success' => true,
                'message' => 'User details fetched successfully',
                'data' => [
                    'unique_id' => $user->unique_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'user_type' => $user->user_type,
                    'last_login_at' => $formatTime($user->last_login_at),
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Fetch user info error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch user information'
            ], 500);
        }
    }

    // ✏ UPDATE USER INFO (ONLY name is allowed)
    public function updateUserInfo(Request $request)
    {
        try {
            $user = $this->validateToken($request);
            if ($user instanceof \Illuminate\Http\JsonResponse)
                return $user;

            $request->validate([
                'name' => 'required|string|max:255'
            ]);

            $user->update([
                'name' => $request->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User information updated successfully',
                'data' => $user
            ]);

        } catch (Exception $e) {
            Log::error("Update user info error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to update user information'
            ], 500);
        }
    }

    // 🗑 DELETE USER (ADMIN ONLY)
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

            // Prevent admin deleting themselves
            if ($admin->unique_id === $user->unique_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin cannot delete their own account'
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
                'message' => 'Unable to delete user'
            ], 500);
        }
    }
}
