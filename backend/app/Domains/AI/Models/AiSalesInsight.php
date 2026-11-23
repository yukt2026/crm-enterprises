<?php

namespace App\Domains\AI\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiSalesInsight extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'summary',
        'extracted_requirements',
        'qualification_status',
        'temperature',
        'intent_score',
        'confidence_score',
        'confidence_rationale',
        'suggested_next_action',
        'objections_detected',
        'deal_risk_factors',
        'sales_pitch',
        'suggested_follow_up_message',
        'suggested_duplicate_lead_id',
        'analyzed_at',
    ];

    protected $casts = [
        'extracted_requirements' => 'array',
        'objections_detected' => 'array',
        'deal_risk_factors' => 'array',
        'intent_score' => 'integer',
        'confidence_score' => 'float',
        'analyzed_at' => 'datetime',
    ];
}
