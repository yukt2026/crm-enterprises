<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmnichannelQuickReply extends Model
{
    use BelongsToTenant;

    protected $table = 'omnichannel_quick_replies';

    protected $fillable = [
        'tenant_id',
        'channel',
        'shortcut',
        'title',
        'content',
        'tags',
        'is_shared',
        'created_by_user_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_shared' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
