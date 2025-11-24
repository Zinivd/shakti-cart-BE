<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'user_name',
        'user_email',
        'user_phone',
        'address_building',
        'address_line1',
        'address_line2',
        'city',
        'district',
        'state',
        'pincode',
        'landmark',
        'address_type',
        'payment_id',
        'payment_mode',
        'payment_status',
        'order_status',
        'total_amount',
        'shipped_at',
        'delivered_at'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }
}
