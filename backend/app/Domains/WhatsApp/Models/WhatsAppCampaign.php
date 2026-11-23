<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Marketing\Models\MarketingSegment;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppCampaign extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'tenant_id',
        'name',
        'whatsapp_phone_number_id',
        'whatsapp_template_id',
        'audience_type', // segment, contacts, csv
        'segment_id',
        'csv_file_path',
        'total_recipients',
        'queued_count',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'replied_count',
        'opted_out_count',
        'estimated_cost',
        'variable_mappings',
        'scheduled_at',
        'status', // draft, pending_approval, approved, queued, processing, paused, completed, cancelled
        'approved_by_user_id',
        'approved_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'total_recipients' => 'integer',
        'queued_count' => 'integer',
        'sent_count' => 'integer',
        'delivered_count' => 'integer',
        'read_count' => 'integer',
        'failed_count' => 'integer',
        'replied_count' => 'integer',
        'opted_out_count' => 'integer',
        'estimated_cost' => 'decimal:4',
        'variable_mappings' => 'array',
        'scheduled_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(MarketingSegment::class, 'segment_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class, 'campaign_id');
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(WhatsAppUsageRecord::class, 'campaign_id');
    }
}
