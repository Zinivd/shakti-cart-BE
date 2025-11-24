<?php

namespace App;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Response;
use App\Models\AuthUser;
use Exception;

trait TokenValidationTrait
{
    /**
     * 🔍 Decrypt and validate token
     */
    private function decryptToken($token)
    {
        try {
            $decrypted = json_decode(Crypt::decryptString($token), true);
            if (!$decrypted || !isset($decrypted['email'])) {
                return null;
            }

            return $decrypted;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * ✅ Validate token for User API
     */
    public function TokenvalidateforUser($request)
    {
        $token = $request->header('Authorization');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token missing'
            ], 401);
        }

        $tokenData = $this->decryptToken($token);
        if (!$tokenData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        $user = AuthUser::where('email', $tokenData['email'])->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->user_type !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied — only users can access this API'
            ], 403);
        }

        return $user;
    }

    /**
     * 🛠️ Validate token for Admin API
     */
    public function TokenvalidateforAdmin($request)
    {
        $token = $request->header('Authorization');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token missing'
            ], 401);
        }

        $tokenData = $this->decryptToken($token);
        if (!$tokenData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        $user = AuthUser::where('email', $tokenData['email'])->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied — only admins can access this API'
            ], 403);
        }

        return $user;
    }
}
