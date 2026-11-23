<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'channel', // whatsapp, email, sms, call, webchat
        'provider_message_id',
        'direction', // inbound, outbound
        'message_type', // text, image, document, audio, video, template, interactive, email, sms, call
        'subject',
        'sender_actor', // customer, human, ai, automation
        'user_id',
        'sender_identity',
        'recipient_identity',
        'in_reply_to',
        'content',
        'media_url',
        'media_id',
        'mime_type',
        'attachments',
        'template_name',
        'template_params',
        'delivery_status', // pending, sent, delivered, read, failed
        'error_code',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'raw_payload',
        'channel_metadata',
    ];

    protected $casts = [
        'template_params' => 'array',
        'attachments' => 'array',
        'raw_payload' => 'array',
        'channel_metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
