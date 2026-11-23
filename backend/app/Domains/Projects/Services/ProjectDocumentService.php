<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use Illuminate\Database\Eloquent\Collection;

class ProjectDocumentService
{
    /**
     * Register a project document or deliverable
     */
    public function uploadDocument(Project $project, array $data, int $userId): ProjectDocument
    {
        $title = $data['name'] ?? ($data['title'] ?? 'Document');
        $fileName = $data['file_name'] ?? basename($data['file_path'] ?? $title);
        $filePath = $data['file_path'] ?? ($data['file_url'] ?? '/uploads/projects/' . $project->id . '/' . $fileName);

        $version = $data['version'] ?? 1;
        if (empty($data['version'])) {
            $existingCount = ProjectDocument::where('project_id', $project->id)
                ->where('title', $title)
                ->count();
            $version = $existingCount + 1;
        }

        return ProjectDocument::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by_user_id' => $userId,
            'title' => $title,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size_kb' => (int) round(($data['file_size'] ?? 1024) / 1024),
            'mime_type' => $data['file_type'] ?? (pathinfo($fileName, PATHINFO_EXTENSION) ?: 'application/pdf'),
            'category' => $data['category'] ?? 'deliverable',
            'version' => $version,
            'is_client_visible' => (bool) ($data['is_client_visible'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Toggle client visibility for document
     */
    public function toggleClientVisibility(ProjectDocument $document, bool $isVisible): ProjectDocument
    {
        $document->update(['is_client_visible' => $isVisible]);
        return $document;
    }

    /**
     * Delete document
     */
    public function deleteDocument(ProjectDocument $document): bool
    {
        return (bool) $document->delete();
    }

    /**
     * Retrieve documents with optional client visibility filter
     */
    public function getDocuments(Project $project, bool $clientOnly = false): Collection
    {
        $query = ProjectDocument::with('uploadedByUser')->where('project_id', $project->id);
        if ($clientOnly) {
            $query->where('is_client_visible', true);
        }
        return $query->orderByDesc('id')->get();
    }
}
