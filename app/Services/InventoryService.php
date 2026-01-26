<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductQuantity;

class InventoryService
{
    /**
     * Sync total_quantity in products table
     */
    public static function syncTotalQuantity(string $productId): void
    {
        $total = ProductQuantity::where('product_id', $productId)
            ->sum('quantity');

        Product::where('product_id', $productId)
            ->update(['total_quantity' => $total]);
    }
}