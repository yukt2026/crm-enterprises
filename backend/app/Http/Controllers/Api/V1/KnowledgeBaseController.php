<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Support\Services\KnowledgeBaseService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected KnowledgeBaseService $kbService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List knowledge base categories.
     */
    public function getCategories(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $categories = $this->kbService->getCategories($tenantId);

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Search and list knowledge articles.
     */
    public function getArticles(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $articles = $this->kbService->getArticles(
            $tenantId,
            $request->input('search'),
            $request->input('category_id') ? (int) $request->input('category_id') : null,
            $request->input('visibility'),
            $request->input('only_published', 'true') === 'true'
        );

        return response()->json([
            'status' => 'success',
            'data' => $articles,
        ]);
    }

    /**
     * Get single article and increment view counter.
     */
    public function getArticle(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $article = $this->kbService->getArticle($tenantId, $id);

        return response()->json([
            'status' => 'success',
            'data' => $article,
        ]);
    }

    /**
     * Create article.
     */
    public function createArticle(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $data = $request->validate([
            'category_id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'tags' => 'nullable|array',
            'visibility' => 'nullable|in:public,internal,customer_portal',
            'is_published' => 'nullable|boolean',
        ]);

        $article = $this->kbService->createArticle($tenantId, $data, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Knowledge base article created.',
            'data' => $article->load('category'),
        ], 201);
    }

    /**
     * Vote on article helpfulness.
     */
    public function voteHelpfulness(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $helpful = (bool) $request->input('helpful', true);
        $article = $this->kbService->voteHelpfulness($tenantId, $id, $helpful);

        return response()->json([
            'status' => 'success',
            'message' => 'Vote recorded.',
            'data' => $article,
        ]);
    }

    /**
     * Suggest AI answer grounded in approved knowledge base articles.
     */
    public function suggestAiAnswer(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = $request->validate([
            'query' => 'required|string',
        ])['query'];

        $suggestion = $this->kbService->suggestAiAnswer($tenantId, $query);

        return response()->json([
            'status' => 'success',
            'data' => $suggestion,
        ]);
    }
}
