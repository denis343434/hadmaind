<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminToken extends Model
{
    protected $fillable = ['admin_id', 'token_hash', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
