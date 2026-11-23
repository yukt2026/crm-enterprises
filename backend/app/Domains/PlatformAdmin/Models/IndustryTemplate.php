<?php

namespace App\Domains\PlatformAdmin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndustryTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'industry_templates';

    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'icon',
        'module_presets',
        'pipeline_stages',
        'custom_fields',
        'dashboard_widgets',
        'automation_recipes',
        'report_definitions',
        'notification_templates',
        'is_active',
    ];

    protected $casts = [
        'module_presets' => 'array',
        'pipeline_stages' => 'array',
        'custom_fields' => 'array',
        'dashboard_widgets' => 'array',
        'automation_recipes' => 'array',
        'report_definitions' => 'array',
        'notification_templates' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = ['template_code'];

    public function getTemplateCodeAttribute(): string
    {
        return (string) ($this->code ?? '');
    }

    public function appliedTenants(): HasMany
    {
        return $this->hasMany(TenantAppliedTemplate::class, 'template_id');
    }
}
