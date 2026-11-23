<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppKnowledgeChunk extends Model
{
    protected $table = 'whatsapp_knowledge_chunks';

    protected $fillable = [
        'tenant_id',
        'knowledge_base_id',
        'chunk_index',
        'content',
        'embedding',
        'source_citation',
        'token_count',
        'metadata',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'embedding' => 'array',
        'token_count' => 'integer',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(WhatsAppKnowledgeBase::class, 'knowledge_base_id');
    }
}
