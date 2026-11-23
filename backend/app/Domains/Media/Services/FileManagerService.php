<?php

namespace App\Domains\Media\Services;

use App\Domains\Media\Models\MediaFile;
use App\Domains\Tenancy\Services\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileManagerService
{
    /**
     * Permitted MIME Types
     */
    public const ALLOWED_MIMES = [
        // Images
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml',
        // Documents
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv', 'text/plain',
        // Audio & Voice
        'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/webm',
        // Archives
        'application/zip', 'application/x-zip-compressed',
    ];

    public const MAX_SIZE_BYTES = 25 * 1024 * 1024; // 25 MB

    /**
     * Upload and store file with tenant isolation
     */
    public function upload(
        UploadedFile $file,
        ?User $uploader = null,
        ?Model $fileable = null,
        bool $isPrivate = true,
        ?int $retentionDays = null
    ): MediaFile {
        $tenantId = TenantContext::getTenantId() ?? $uploader?->current_tenant_id ?? 1;
        $uuid = (string) Str::uuid();

        // 1. Calculate SHA256 Hash
        $hash = hash_file('sha256', $file->getRealPath());

        // 2. Prepare Isolated Storage Path
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $fileName = "{$uuid}_{$safeName}.{$ext}";
        $yearMonth = date('Y/m');
        $directory = "tenants/{$tenantId}/{$yearMonth}";
        $storagePath = "{$directory}/{$fileName}";

        // 3. Store on disk
        $disk = 'local';
        Storage::disk($disk)->putFileAs($directory, $file, $fileName);

        // 4. Create Metadata
        $metadata = [
            'original_extension' => $ext,
            'client_mime' => $file->getClientMimeType(),
        ];

        // 5. Save MediaFile record
        return MediaFile::create([
            'uuid' => $uuid,
            'tenant_id' => $tenantId,
            'user_id' => $uploader?->id,
            'fileable_type' => $fileable ? get_class($fileable) : null,
            'fileable_id' => $fileable?->getKey(),
            'file_name' => $fileName,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
            'disk' => $disk,
            'storage_path' => $storagePath,
            'hash' => $hash,
            'is_private' => $isPrivate,
            'retention_days' => $retentionDays,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Delete a file from disk and database
     */
    public function delete(MediaFile $mediaFile, bool $force = false): bool
    {
        if ($force) {
            if (Storage::disk($mediaFile->disk)->exists($mediaFile->storage_path)) {
                Storage::disk($mediaFile->disk)->delete($mediaFile->storage_path);
            }
            return (bool) $mediaFile->forceDelete();
        }

        return (bool) $mediaFile->delete();
    }
}
