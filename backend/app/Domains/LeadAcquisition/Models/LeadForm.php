<?php

namespace App\Domains\LeadAcquisition\Models;

use App\Domains\Tenancy\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'lead_source_id',
        'provider',
        'name',
        'external_form_id',
        'public_uuid',
        'fields_config',
        'is_active',
        'campaign_id',
        'campaign_name',
        'ad_id',
        'submissions_count',
        'last_submission_at',
    ];

    protected $casts = [
        'fields_config' => 'array',
        'is_active' => 'boolean',
        'last_submission_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($form) {
            if (empty($form->public_uuid)) {
                $form->public_uuid = (string) Str::uuid();
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
