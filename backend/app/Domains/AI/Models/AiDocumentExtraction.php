<?php

namespace App\Domains\AI\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDocumentExtraction extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'uploaded_by_user_id',
        'original_filename',
        'file_path',
        'file_type',
        'document_classification',
        'processing_status',
        'overall_confidence',
        'extracted_fields',
        'field_confidences',
        'user_corrections',
        'committed_to_entity_id',
        'committed_to_entity_type',
        'committed_at',
        'error_message',
    ];

    protected $casts = [
        'overall_confidence' => 'float',
        'extracted_fields' => 'array',
        'field_confidences' => 'array',
        'user_corrections' => 'array',
        'committed_at' => 'datetime',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
