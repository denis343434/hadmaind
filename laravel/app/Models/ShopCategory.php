<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopCategory extends Model
{
    protected $fillable = ['name', 'slug', 'title', 'subtitle', 'short_name', 'sort', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(ShopProduct::class, 'category_id');
    }
}
