<?php

namespace App\Domains\CRM\Models;

use App\Domains\CustomFields\Traits\HasCustomFields;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasCustomFields;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'name',
        'legal_name',
        'industry',
        'website',
        'size',
        'annual_revenue',
        'gstin_tax_id',
        'registration_number',
        'address',
        'phone',
        'billing_address',
        'billing_city',
        'billing_state',
        'billing_country',
        'billing_postal_code',
        'delivery_address',
        'delivery_city',
        'delivery_state',
        'delivery_country',
        'delivery_postal_code',
        'account_owner_id',
        'account_status',
        'tags',
    ];

    protected $casts = [
        'annual_revenue' => 'decimal:2',
        'tags' => 'array',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function accountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_owner_id');
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
