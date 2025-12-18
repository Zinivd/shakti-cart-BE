<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuthUser;

class ProductReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'product_id',
        'user_id',
        'is_admin',
        'admin_name',
        'admin_email',
        'title',
        'description',
        'rating'
    ];

    public function user()
    {
        return $this->belongsTo(AuthUser::class, 'user_id', 'unique_id');
    }
}