<?php

namespace App\Domains\Media\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class MediaFile extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'fileable_type',
        'fileable_id',
        'file_name',
        'original_name',
        'mime_type',
        'file_size',
        'disk',
        'storage_path',
        'hash',
        'is_private',
        'retention_days',
        'metadata',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'file_size' => 'integer',
        'retention_days' => 'integer',
        'metadata' => 'array',
    ];

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Generate secure temporary signed URL for private access
     */
    public function getSignedUrl(int $expirationMinutes = 30): string
    {
        return URL::temporarySignedRoute(
            'api.v1.media.download',
            now()->addMinutes($expirationMinutes),
            ['uuid' => $this->uuid]
        );
    }
}
