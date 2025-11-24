<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\Crypt;

class CartController extends Controller
{
    // ➕ Add to Cart
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,product_id',
            'quantity' => 'nullable|integer|min:1'
        ]);

        // ✅ Extract user from token
        $token = $request->header('Authorization');
        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Token missing'], 401);
        }

        try {
            $token = str_replace('Bearer ', '', $token);
            $decoded = json_decode(Crypt::decryptString($token), true);
            $userId = $decoded['unique_id'];
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
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
                'quantity' => $request->quantity ?? 1
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Product added to cart']);
    }

    // 🧾 Get Cart List
    public function getCartItems(Request $request)
    {
        try {
            $token = str_replace('Bearer ', '', $request->header('Authorization'));
            $decoded = json_decode(Crypt::decryptString($token), true);
            $userId = $decoded['unique_id'];
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
        }

        $cartItems = CartItem::where('user_id', $userId)->with('product')->get();

        return response()->json([
            'success' => true,
            'count' => $cartItems->count(),
            'data' => $cartItems
        ]);
    }

    public function removeCartItem(Request $request)
{
    $request->validate([
        'product_id' => 'required|exists:products,product_id',
    ]);

    try {
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        $decoded = json_decode(\Crypt::decryptString($token), true);
        $userId = $decoded['unique_id'];
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
    }

    $item = CartItem::where('user_id', $userId)
        ->where('product_id', $request->product_id)
        ->first();

    if (!$item) {
        return response()->json(['success' => false, 'message' => 'Item not found in cart']);
    }

    $item->delete();

    return response()->json(['success' => true, 'message' => 'Item removed from cart']);
}
}
