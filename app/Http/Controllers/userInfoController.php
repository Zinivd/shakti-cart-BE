<?php

namespace App\Http\Controllers;

use App\Models\AuthUser;
use Illuminate\Http\Request;
use Carbon\Carbon;

class userInfoController extends Controller
{
    public function userInfo(Request $request)
    {
        $email = $request->query('email');
        $token = $request->header('Authorization');

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'Email parameter is required'
            ]);
        }

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token missing'
            ]);
        }

        $token = str_replace('Bearer ', '', $token);

        $user = AuthUser::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ]);
        }

        if ($user->session_token !== $token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ]);
        }

        // Convert timestamps safely
        $formatTime = function ($value) {
            return $value ? Carbon::parse($value)->setTimezone('Asia/Kolkata')->toDateTimeString() : null;
        };

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
    }
}
