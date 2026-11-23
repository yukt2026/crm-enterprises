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

class Customer extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasCustomFields;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'contact_id',
        'lead_id',
        'name',
        'email',
        'phone',
        'status',
        'tier',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
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
