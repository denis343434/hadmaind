<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopOrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'slug', 'name', 'price', 'qty', 'total', 'image_url'];

    protected $casts = [
        'price' => 'integer',
        'qty' => 'integer',
        'total' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'product_id');
    }
}
