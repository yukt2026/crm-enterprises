<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppConversationNote extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'whatsapp_conversation_notes';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'user_id',
        'content',
        'mentions',
    ];

    protected $casts = [
        'mentions' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
