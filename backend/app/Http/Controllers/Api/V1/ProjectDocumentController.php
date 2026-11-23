<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Domains\Projects\Services\ProjectDocumentService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectDocumentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected ProjectDocumentService $documentService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(Request $request, int $projectId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($projectId);

        $clientOnly = $request->boolean('client_only');
        $docs = $this->documentService->getDocuments($project, $clientOnly);

        return $this->successResponse($docs);
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($projectId);

        if (!$request->filled('name') && $request->filled('title')) {
            $request->merge(['name' => $request->title]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'file_name' => 'nullable|string|max:255',
            'file_path' => 'nullable|string|max:500',
            'file_url' => 'nullable|string|max:500',
            'file_type' => 'nullable|string|max:50',
            'file_size' => 'nullable|integer',
            'category' => 'nullable|string|in:deliverable,design,agreement,spec,invoice,other',
            'version' => 'nullable|integer',
            'is_client_visible' => 'nullable|boolean',
        ]);

        $doc = $this->documentService->uploadDocument($project, $validated, auth()->id() ?? 1);

        return $this->successResponse($doc->load('uploadedByUser'), 'Document uploaded successfully.', 201);
    }

    public function toggleVisibility(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $doc = ProjectDocument::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'is_client_visible' => 'required|boolean',
        ]);

        $doc = $this->documentService->toggleClientVisibility($doc, $validated['is_client_visible']);

        return $this->successResponse($doc, 'Document visibility updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $doc = ProjectDocument::where('tenant_id', $tenantId)->findOrFail($id);

        $this->documentService->deleteDocument($doc);

        return $this->successResponse(null, 'Document removed successfully.');
    }
}
