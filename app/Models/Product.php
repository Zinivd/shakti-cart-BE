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
        'size_unit',
        'actual_price',
        'discount',
        'selling_price',
        'product_list_type',
        'product_specification',
        'images'
    ];

    protected $casts = [
        'size_unit' => 'array',
        'images' => 'array',
         'product_specification' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id', 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(ProductSubCategory::class, 'sub_category_id', 'sub_category_id');
    }
}
