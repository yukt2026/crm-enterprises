<?php

namespace App\Domains\Integrations\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthState extends Model
{
    use HasFactory, BelongsToTenant;

    public $timestamps = false;
    protected $table = 'oauth_states';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'provider',
        'state_token',
        'redirect_url',
        'expires_at',
        'used_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
