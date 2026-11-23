<?php

namespace App\Domains\Projects\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDocument extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'uploaded_by_user_id',
        'title',
        'file_name',
        'file_path',
        'file_size_kb',
        'mime_type',
        'category',
        'version',
        'is_client_visible',
        'notes',
    ];

    protected $casts = [
        'is_client_visible' => 'boolean',
        'version' => 'integer',
        'file_size_kb' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
