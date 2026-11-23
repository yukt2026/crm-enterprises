<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebsiteChatWidget extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'website_chat_widgets';

    protected $fillable = [
        'tenant_id',
        'name',
        'widget_key',
        'brand_color',
        'brand_title',
        'brand_logo_url',
        'welcome_message',
        'pre_chat_fields',
        'agent_availability_mode',
        'is_active',
        'allowed_domains',
        'settings',
    ];

    protected $casts = [
        'pre_chat_fields' => 'array',
        'allowed_domains' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];
}
