<?php

namespace App\Domains\LandingPages\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandingPage extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'title',
        'description',
        'status', // draft, published, unpublished, archived
        'custom_domain_id',
        'current_version',
        'branding',
        'seo_metadata',
        'sections',
        'settings',
        'view_count',
        'submission_count',
        'published_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'branding' => 'array',
        'seo_metadata' => 'array',
        'sections' => 'array',
        'settings' => 'array',
        'published_at' => 'datetime',
    ];

    public function customDomain(): BelongsTo
    {
        return $this->belongsTo(CustomDomain::class, 'custom_domain_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(LandingPageRevision::class, 'landing_page_id')->orderByDesc('version');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'landing_page_id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(LandingPageAnalytics::class, 'landing_page_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
