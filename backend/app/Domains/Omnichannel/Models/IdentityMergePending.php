<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityMergePending extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'identity_merges_pending';

    protected $fillable = [
        'tenant_id',
        'primary_type', // lead, contact, customer
        'primary_id',
        'secondary_type',
        'secondary_id',
        'match_criteria', // email, phone, name_similarity
        'confidence_score',
        'status', // pending_review, merged, rejected
        'reviewed_by_user_id',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'confidence_score' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
