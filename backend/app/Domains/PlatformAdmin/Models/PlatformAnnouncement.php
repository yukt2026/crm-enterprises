<?php

namespace App\Domains\PlatformAdmin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAnnouncement extends Model
{
    use HasFactory;

    protected $table = 'platform_announcements';

    protected $fillable = [
        'title',
        'content',
        'type', // info, warning, maintenance, critical
        'target_audience', // all, paid_only, specific_tenants
        'target_tenant_ids',
        'starts_at',
        'expires_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'target_tenant_ids' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $appends = ['message'];

    public function getMessageAttribute(): string
    {
        return (string) ($this->content ?? '');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
