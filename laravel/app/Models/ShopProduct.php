<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopProduct extends Model
{
    protected $fillable = [
        'category_id',
        'slug',
        'name',
        'price',
        'old_price',
        'badge',
        'short_description',
        'description',
        'specs',
        'image_url',
        'tone',
        'zoom',
        'is_featured',
        'is_active',
        'sort',
    ];

    protected $casts = [
        'price' => 'integer',
        'old_price' => 'integer',
        'specs' => 'array',
        'zoom' => 'float',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class, 'category_id');
    }
}
