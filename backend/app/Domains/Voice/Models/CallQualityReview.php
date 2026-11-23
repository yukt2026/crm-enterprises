<?php

namespace App\Domains\Voice\Models;

use App\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallQualityReview extends Model
{
    protected $table = 'call_quality_reviews';

    protected $fillable = [
        'tenant_id',
        'voice_call_id',
        'reviewer_user_id',
        'score',
        'criteria_scores',
        'feedback',
        'tags',
    ];

    protected $casts = [
        'score' => 'integer',
        'criteria_scores' => 'array',
        'tags' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(VoiceCall::class, 'voice_call_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
