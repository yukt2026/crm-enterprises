<?php

namespace App\Domains\AI\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMeetingRecording extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'meeting_id',
        'created_by_user_id',
        'title',
        'audio_file_path',
        'duration_seconds',
        'language',
        'processing_status',
        'raw_transcript',
        'meeting_summary',
        'key_discussion_points',
        'customer_requirements',
        'objections_raised',
        'action_items',
        'linked_contact_id',
        'linked_deal_id',
        'error_message',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'key_discussion_points' => 'array',
        'customer_requirements' => 'array',
        'objections_raised' => 'array',
        'action_items' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
