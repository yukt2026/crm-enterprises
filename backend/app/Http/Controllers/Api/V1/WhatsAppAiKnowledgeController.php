<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\WhatsApp\AI\Knowledge\KnowledgeBaseService;
use App\Domains\WhatsApp\Models\WhatsAppKnowledgeBase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppAiKnowledgeController extends Controller
{
    protected KnowledgeBaseService $knowledgeService;

    public function __construct(KnowledgeBaseService $knowledgeService)
    {
        $this->knowledgeService = $knowledgeService;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $items = WhatsAppKnowledgeBase::where('tenant_id', $tenantId)
            ->withCount('chunks')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $request->validate([
            'title' => 'required|string|max:191',
            'category' => 'required|string|in:faqs,catalog,policies,manuals,web_crawl',
            'description' => 'nullable|string',
            'source_type' => 'required|in:file,manual_text',
            'file' => 'nullable|file|max:20480', // Up to 20MB
            'content' => 'nullable|string',
        ]);

        $title = $request->input('title');
        $category = $request->input('category');
        $description = $request->input('description');
        $sourceType = $request->input('source_type');

        if ($sourceType === 'file') {
            if (!$request->hasFile('file')) {
                return response()->json(['success' => false, 'message' => 'Please provide a file to upload.'], 422);
            }
            $file = $request->file('file');
            $kb = $this->knowledgeService->ingestFile($tenantId, $file, $title, $category, $description);
        } else {
            $content = $request->input('content');
            if (empty(trim($content ?: ''))) {
                return response()->json(['success' => false, 'message' => 'Knowledge content cannot be empty.'], 422);
            }
            $kb = $this->knowledgeService->ingestText($tenantId, $title, $content, $category, $description);
        }

        return response()->json([
            'success' => true,
            'data' => $kb->loadCount('chunks'),
            'message' => 'Knowledge base document ingested and indexed successfully.',
        ], 201);
    }

    public function search(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $request->validate([
            'query' => 'required|string|min:2',
            'top_k' => 'nullable|integer|min:1|max:10',
        ]);

        $query = $request->input('query');
        $topK = $request->input('top_k', 3);

        $results = $this->knowledgeService->searchKnowledge($tenantId, $query, $topK);

        return response()->json([
            'success' => true,
            'query' => $query,
            'count' => count($results),
            'data' => $results,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $kb = WhatsAppKnowledgeBase::where('tenant_id', $tenantId)->findOrFail($id);
        $kb->delete();

        return response()->json([
            'success' => true,
            'message' => 'Knowledge base document and indexed chunks removed.',
        ]);
    }
}
