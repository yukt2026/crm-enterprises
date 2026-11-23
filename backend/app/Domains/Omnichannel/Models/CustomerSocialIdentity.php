<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSocialIdentity extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'customer_social_identities';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'lead_id',
        'contact_id',
        'provider', // 'facebook' or 'instagram'
        'scoped_id', // PSID or IGSID
        'display_name',
        'username',
        'profile_pic_url',
        'is_verified',
        'merged_with_id',
        'metadata',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'metadata' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function mergedWith(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_with_id');
    }

    public function scopeFacebook(Builder $query): Builder
    {
        return $query->where('provider', 'facebook');
    }

    public function scopeInstagram(Builder $query): Builder
    {
        return $query->where('provider', 'instagram');
    }
}
