<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\AuditSecurity\Services\AuditLogger;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Media\Services\FileManagerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class MediaFileController extends Controller
{
    public function __construct(
        protected FileManagerService $fileManager
    ) {}

    /**
     * List tenant files
     */
    public function index(Request $request): JsonResponse
    {
        $query = MediaFile::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($request->has('mime_type')) {
            $query->where('mime_type', 'like', $request->input('mime_type') . '%');
        }

        if ($request->has('fileable_type') && $request->has('fileable_id')) {
            $query->where('fileable_type', $request->input('fileable_type'))
                  ->where('fileable_id', $request->input('fileable_id'));
        }

        $perPage = min((int) $request->input('per_page', 15), 50);
        $files = $query->paginate($perPage);

        // Append temporary signed URLs for private downloads
        $items = collect($files->items())->map(function (MediaFile $file) {
            $data = $file->toArray();
            $data['download_url'] = $file->getSignedUrl(60);
            return $data;
        });

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Files retrieved.',
            'data' => $items,
            'meta' => [
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
            ],
        ]);
    }

    /**
     * Upload a new media file
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . (FileManagerService::MAX_SIZE_BYTES / 1024),
                function ($attribute, $value, $fail) {
                    if (!in_array($value->getMimeType(), FileManagerService::ALLOWED_MIMES)) {
                        $fail("The file type ({$value->getMimeType()}) is not permitted.");
                    }
                },
            ],
            'fileable_type' => 'nullable|string',
            'fileable_id' => 'nullable|integer',
            'is_private' => 'nullable|boolean',
            'retention_days' => 'nullable|integer|min:1',
        ]);

        $uploader = Auth::user();
        $isPrivate = $request->boolean('is_private', true);
        $retentionDays = $request->input('retention_days') ? (int) $request->input('retention_days') : null;

        // Resolve optional polymorphic attachment
        $fileable = null;
        if ($request->filled('fileable_type') && $request->filled('fileable_id')) {
            $modelClass = $request->input('fileable_type');
            if (class_exists($modelClass)) {
                $fileable = $modelClass::find($request->input('fileable_id'));
            }
        }

        $mediaFile = $this->fileManager->upload(
            $request->file('file'),
            $uploader,
            $fileable,
            $isPrivate,
            $retentionDays
        );

        // Audit Log entry
        AuditLogger::log('uploaded_file', $mediaFile, null, [
            'file_name' => $mediaFile->file_name,
            'original_name' => $mediaFile->original_name,
            'file_size' => $mediaFile->file_size,
        ]);

        $fileData = $mediaFile->toArray();
        $fileData['download_url'] = $mediaFile->getSignedUrl(60);

        return response()->json([
            'success' => true,
            'status_code' => 201,
            'message' => 'File uploaded successfully.',
            'data' => $fileData,
        ], 201);
    }

    /**
     * Get file metadata
     */
    public function show(string $uuid): JsonResponse
    {
        $mediaFile = MediaFile::where('uuid', $uuid)->firstOrFail();
        $fileData = $mediaFile->toArray();
        $fileData['download_url'] = $mediaFile->getSignedUrl(60);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'File details retrieved.',
            'data' => $fileData,
        ]);
    }

    /**
     * Securely download or stream file (validates signed URL or authenticated tenant user)
     */
    public function download(Request $request, string $uuid): BinaryFileResponse|Response
    {
        // 1. Verify URL signature if unauthenticated, or verify tenant context if authenticated
        if (!$request->hasValidSignature() && !Auth::check()) {
            abort(403, 'Invalid or expired file access signature.');
        }

        $mediaFile = MediaFile::where('uuid', $uuid)->firstOrFail();

        // 2. Tenant isolation check if authenticated
        if (Auth::check()) {
            $user = Auth::user();
            if (!$user->is_platform_admin && $user->current_tenant_id && $mediaFile->tenant_id !== $user->current_tenant_id) {
                abort(403, 'Unauthorized file access across tenant boundary.');
            }
        }

        // 3. Verify file exists on disk
        $disk = Storage::disk($mediaFile->disk);
        if (!$disk->exists($mediaFile->storage_path)) {
            abort(404, 'File not found on storage disk.');
        }

        $path = $disk->path($mediaFile->storage_path);

        return response()->download($path, $mediaFile->original_name, [
            'Content-Type' => $mediaFile->mime_type,
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ]);
    }

    /**
     * Delete a file
     */
    public function destroy(string $uuid): JsonResponse
    {
        $mediaFile = MediaFile::where('uuid', $uuid)->firstOrFail();

        AuditLogger::log('deleted_file', $mediaFile, [
            'file_name' => $mediaFile->file_name,
            'original_name' => $mediaFile->original_name,
        ], null);

        $this->fileManager->delete($mediaFile);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'File removed successfully.',
        ]);
    }
}
