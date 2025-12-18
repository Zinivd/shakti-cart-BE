<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'category_name', 'image'];

    public function subcategories()
    {
        return $this->hasMany(ProductSubCategory::class, 'category_id', 'category_id');
    }
}
