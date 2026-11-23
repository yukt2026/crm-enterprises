<?php

namespace App\Domains\Support\Models;

use App\Domains\CRM\Models\Customer;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'sender_type', // user, customer, system, ai
        'sender_id',
        'message',
        'is_internal', // true = internal note, false = customer visible
        'channel', // email, whatsapp, web_chat, internal
        'attachments',
        'is_ai_generated',
        'is_draft',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_ai_generated' => 'boolean',
        'is_draft' => 'boolean',
        'attachments' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'sender_id');
    }
}
