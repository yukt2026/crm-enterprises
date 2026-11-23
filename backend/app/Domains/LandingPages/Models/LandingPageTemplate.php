<?php

namespace App\Domains\LandingPages\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingPageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'description',
        'preview_image',
        'branding',
        'sections',
        'is_system',
    ];

    protected $casts = [
        'branding' => 'array',
        'sections' => 'array',
        'is_system' => 'boolean',
    ];
}
