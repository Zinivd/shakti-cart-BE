<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'auth_user_id',
        'name',
        'phone',
        'building_name',
        'address_1',
        'address_2',
        'city',
        'district',
        'state',
        'pincode',
        'landmark',
        'address_type',
    ];

    public function user()
    {
        return $this->belongsTo(AuthUser::class, 'auth_user_id');
    }
}