<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WishlistItem;
use App\Models\Product;
use Illuminate\Support\Facades\Crypt;

class WishlistController extends Controller
{
    // ➕ Add to Wishlist
    public function addToWishlist(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,product_id',
        ]);

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

        $exists = WishlistItem::where('user_id', $userId)
            ->where('product_id', $request->product_id)
            ->first();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Product already in wishlist']);
        }

        WishlistItem::create([
            'user_id' => $userId,
            'product_id' => $request->product_id
        ]);

        return response()->json(['success' => true, 'message' => 'Product added to wishlist']);
    }

    // 📋 Get Wishlist
    public function getWishlistItems(Request $request)
    {
        try {
            $token = str_replace('Bearer ', '', $request->header('Authorization'));
            $decoded = json_decode(Crypt::decryptString($token), true);
            $userId = $decoded['unique_id'];
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
        }

        $wishlist = WishlistItem::where('user_id', $userId)->with('product')->get();

        return response()->json([
            'success' => true,
            'count' => $wishlist->count(),
            'data' => $wishlist
        ]);
    }

    public function removeWishlistItem(Request $request)
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

    $item = WishlistItem::where('user_id', $userId)
        ->where('product_id', $request->product_id)
        ->first();

    if (!$item) {
        return response()->json(['success' => false, 'message' => 'Item not found in wishlist']);
    }

    $item->delete();

    return response()->json(['success' => true, 'message' => 'Item removed from wishlist']);
}
}
