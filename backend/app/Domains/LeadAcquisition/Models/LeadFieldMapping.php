<?php

namespace App\Domains\LeadAcquisition\Models;

use App\Domains\Tenancy\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFieldMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'lead_source_id',
        'provider',
        'source_field',
        'crm_field',
        'transform_rule',
        'default_value',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }
}
