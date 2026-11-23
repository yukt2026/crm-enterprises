<?php

namespace App\Domains\PlatformAdmin\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAppliedTemplate extends Model
{
    use HasFactory;

    protected $table = 'tenant_applied_templates';

    protected $fillable = [
        'tenant_id',
        'template_id',
        'template_code',
        'customizations',
        'applied_at',
        'status', // active, customized, disabled
    ];

    protected $casts = [
        'customizations' => 'array',
        'applied_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(IndustryTemplate::class, 'template_id');
    }
}
