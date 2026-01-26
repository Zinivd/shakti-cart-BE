<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_name',
        'brand',
        'category_id',
        'category_name',
        'sub_category_id',
        'sub_category_name',
        'description',
        'color',

        // size + stock
        'size_unit',
        'total_quantity',

        // pricing
        'actual_price',
        'discount',
        'selling_price',

        // meta
        'product_list_type',
        'product_specification',
        'images'
    ];

    protected $casts = [
        'size_unit' => 'array',
        'images' => 'array',
        'product_specification' => 'array',
    ];

    // --------------------------------------------------
    // 🔗 RELATIONSHIPS
    // --------------------------------------------------

    public function category()
    {
        return $this->belongsTo(
            ProductCategory::class,
            'category_id',
            'category_id'
        );
    }

    public function subcategory()
    {
        return $this->belongsTo(
            ProductSubCategory::class,
            'sub_category_id',
            'sub_category_id'
        );
    }

    /**
     * Size-wise stock
     */
    public function quantities()
    {
        return $this->hasMany(
            ProductQuantity::class,
            'product_id',
            'product_id'
        );
    }

    // --------------------------------------------------
    // 🔥 HELPER: AVAILABLE STOCK (OPTIONAL BUT USEFUL)
    // --------------------------------------------------

    /**
     * Get total available quantity (from quantities table)
     */
    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantities()->sum('quantity');
    }
}
