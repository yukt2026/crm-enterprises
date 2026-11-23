<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingTemplate extends Model
{
    use HasFactory;

    protected $table = 'marketing_templates';

    protected $fillable = [
        'tenant_id',
        'name',
        'channel',
        'category',
        'subject',
        'content',
        'variables',
        'dlt_template_id',
        'status',
        'version',
        'created_by_user_id',
    ];

    protected $casts = [
        'variables' => 'array',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
