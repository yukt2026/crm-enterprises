<?php

namespace App\Domains\CRM\Models;

use App\Domains\CustomFields\Traits\HasCustomFields;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasCustomFields;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'lead_id',
        'branch_id',
        'name',
        'email',
        'email_addresses',
        'phone',
        'phone_numbers',
        'job_title',
        'department',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'communication_preferences',
        'consent_status',
        'consent_recorded_at',
        'tags',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'email_addresses' => 'array',
        'phone_numbers' => 'array',
        'communication_preferences' => 'array',
        'tags' => 'array',
        'consent_recorded_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class)->orderBy('created_at', 'desc');
    }

    public function mediaFiles(): MorphMany
    {
        return $this->morphMany(MediaFile::class, 'fileable');
    }
}
