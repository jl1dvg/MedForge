<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'service_mode',
        'readonly_start',
        'readonly_end',
        'readonly_message',
        'is_active',
    ];

    protected $casts = [
        'readonly_start' => 'datetime',
        'readonly_end'   => 'datetime',
        'is_active'      => 'boolean',
    ];
}
