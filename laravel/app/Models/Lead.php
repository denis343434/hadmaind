<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = ['type', 'name', 'phone', 'email', 'message', 'meta', 'status'];

    protected $casts = [
        'meta' => 'array',
    ];
}
