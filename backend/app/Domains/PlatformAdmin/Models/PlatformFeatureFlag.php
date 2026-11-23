<?php

namespace App\Domains\PlatformAdmin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeatureFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'flag_key',
        'name',
        'description',
        'is_enabled',
        'rules',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'rules' => 'array',
    ];
}
