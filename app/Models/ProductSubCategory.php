<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSubCategory extends Model
{
    use HasFactory;

    protected $table = 'product_subcategories'; // 👈 Fix table name

    protected $fillable = [
        'sub_category_id',
        'sub_category_name',
        'category_id'
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id', 'category_id');
    }
}
