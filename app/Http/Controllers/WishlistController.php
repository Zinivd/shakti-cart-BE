<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WishlistItem;
use App\Models\Product;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Exception;

class WishlistController extends Controller
{
    // ---------------------------------------------------------------------
    // 🔐 TOKEN VALIDATION HELPER
    // ---------------------------------------------------------------------
    private function getUserFromToken(Request $request)
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

            if (!isset($decoded['unique_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token structure'
                ], 401);
            }

            return $decoded;

        } catch (Exception $e) {
            Log::error("Token Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Invalid or expired token'
            ], 401);
        }
    }

    // ---------------------------------------------------------------------
    // ➕ ADD TO WISHLIST
    // ---------------------------------------------------------------------
    public function addToWishlist(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,product_id',
            ]);

            // User from token
            $decoded = $this->getUserFromToken($request);
            if ($decoded instanceof \Illuminate\Http\JsonResponse)
                return $decoded;

            $userId = $decoded['unique_id'];

            // Check duplicate
            $exists = WishlistItem::where('user_id', $userId)
                ->where('product_id', $request->product_id)
                ->first();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product already in wishlist'
                ]);
            }

            // Add to wishlist
            WishlistItem::create([
                'user_id' => $userId,
                'product_id' => $request->product_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product added to wishlist'
            ]);

        } catch (Exception $e) {
            Log::error("AddToWishlist Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Failed to add product to wishlist'
            ], 500);
        }
    }

    // ---------------------------------------------------------------------
    // 📋 GET USER WISHLIST
    // ---------------------------------------------------------------------
    public function getWishlistItems(Request $request)
    {
        try {
            $decoded = $this->getUserFromToken($request);
            if ($decoded instanceof \Illuminate\Http\JsonResponse)
                return $decoded;

            $userId = $decoded['unique_id'];

            $wishlist = WishlistItem::where('user_id', $userId)
                ->with('product')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $wishlist->count(),
                'data' => $wishlist
            ]);

        } catch (Exception $e) {
            Log::error("GetWishlist Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Unable to fetch wishlist'
            ], 500);
        }
    }

    // ---------------------------------------------------------------------
    // ❌ REMOVE FROM WISHLIST
    // ---------------------------------------------------------------------
    public function removeWishlistItem(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,product_id'
            ]);

            $decoded = $this->getUserFromToken($request);
            if ($decoded instanceof \Illuminate\Http\JsonResponse)
                return $decoded;

            $userId = $decoded['unique_id'];

            $item = WishlistItem::where('user_id', $userId)
                ->where('product_id', $request->product_id)
                ->first();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in wishlist'
                ]);
            }

            $item->delete();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from wishlist'
            ]);

        } catch (Exception $e) {
            Log::error("RemoveWishlist Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Failed to remove item from wishlist'
            ], 500);
        }
    }
}
