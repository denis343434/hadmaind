<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopOrder extends Model
{
    protected $fillable = [
        'code',
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_method',
        'city',
        'address',
        'comment',
        'payment',
        'subtotal',
        'delivery_price',
        'total',
        'status',
        'admin_note',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'delivery_price' => 'integer',
        'total' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class, 'order_id');
    }
}
