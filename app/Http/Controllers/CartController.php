<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Exception;

class CartController extends Controller
{
    // ------------------------------------------------------------------
    // 🔐 TOKEN VALIDATION (Reusable)
    // ------------------------------------------------------------------
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

            return $decoded['unique_id'];

        } catch (Exception $e) {
            Log::error("Token Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Invalid or expired token'
            ], 401);
        }
    }

    // ------------------------------------------------------------------
    // ➕ ADD TO CART
    // ------------------------------------------------------------------
    public function addToCart(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,product_id',
                'quantity' => 'nullable|integer|min:1',
                'size' => 'nullable|string'
            ]);

            $userId = $this->getUserFromToken($request);

            if ($userId instanceof \Illuminate\Http\JsonResponse) {
                return $userId;
            }

            $existing = CartItem::where('user_id', $userId)
                ->where('product_id', $request->product_id)
                ->first();

            if ($existing) {
                $existing->quantity += $request->quantity ?? 1;
                $existing->save();
            } else {
                CartItem::create([
                    'user_id' => $userId,
                    'product_id' => $request->product_id,
                    'size' => $request->size,
                    'quantity' => $request->quantity ?? 1
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product added to cart'
            ]);

        } catch (Exception $e) {
            Log::error("Cart Add Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Unable to add product to cart'
            ], 500);
        }
    }

    // ------------------------------------------------------------------
    // 🧾 GET CART ITEMS
    // ------------------------------------------------------------------
    public function getCartItems(Request $request)
    {
        try {
            $userId = $this->getUserFromToken($request);

            if ($userId instanceof \Illuminate\Http\JsonResponse) {
                return $userId;
            }

            $cartItems = CartItem::where('user_id', $userId)
                ->with('product')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $cartItems->count(),
                'data' => $cartItems
            ]);

        } catch (Exception $e) {
            Log::error("Get Cart Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Unable to fetch cart items'
            ], 500);
        }
    }

    // ------------------------------------------------------------------
    // ❌ REMOVE CART ITEM
    // ------------------------------------------------------------------
    public function removeCartItem(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,product_id',
            ]);

            $userId = $this->getUserFromToken($request);

            if ($userId instanceof \Illuminate\Http\JsonResponse) {
                return $userId;
            }

            $item = CartItem::where('user_id', $userId)
                ->where('product_id', $request->product_id)
                ->first();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in cart'
                ], 404);
            }

            $item->delete();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart'
            ]);

        } catch (Exception $e) {
            Log::error("Remove Cart Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Unable to remove item from cart'
            ], 500);
        }
    }
}
