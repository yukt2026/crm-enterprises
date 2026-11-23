<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppKnowledgeBase extends Model
{
    protected $table = 'whatsapp_knowledge_bases';

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'category',
        'source_type',
        'file_path',
        'file_type',
        'file_size_bytes',
        'status',
        'error_message',
        'total_chunks',
        'version',
        'metadata',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'total_chunks' => 'integer',
        'version' => 'integer',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(WhatsAppKnowledgeChunk::class, 'knowledge_base_id')->orderBy('chunk_index', 'asc');
    }
}
