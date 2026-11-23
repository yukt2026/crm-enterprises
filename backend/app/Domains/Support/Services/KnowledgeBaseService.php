<?php

namespace App\Domains\Support\Services;

use App\Domains\Support\Models\KnowledgeArticle;
use App\Domains\Support\Models\KnowledgeCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class KnowledgeBaseService
{
    /**
     * Get or seed default Knowledge Base categories.
     */
    public function getCategories(int $tenantId): Collection
    {
        $count = KnowledgeCategory::where('tenant_id', $tenantId)->count();
        if ($count === 0) {
            $this->seedDefaultCategories($tenantId);
        }

        return KnowledgeCategory::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->withCount('articles')
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    /**
     * Seed default Knowledge Categories and Articles.
     */
    protected function seedDefaultCategories(int $tenantId): void
    {
        $categories = [
            ['name' => 'Getting Started & Setup', 'slug' => 'getting-started', 'icon' => 'BookOpen', 'description' => 'Platform onboarding and initial workspace setup.'],
            ['name' => 'Billing & Payment Gateways', 'slug' => 'billing-payments', 'icon' => 'CreditCard', 'description' => 'GST invoices, Razorpay, UPI QR and payment reconciliation.'],
            ['name' => 'WhatsApp & Omnichannel Inbox', 'slug' => 'whatsapp-omnichannel', 'icon' => 'MessageSquare', 'description' => 'Meta Cloud API setup, shared inboxes and template rules.'],
            ['name' => 'Troubleshooting & Error Guides', 'slug' => 'troubleshooting', 'icon' => 'AlertCircle', 'description' => 'Frequent technical questions and step-by-step resolution steps.'],
        ];

        foreach ($categories as $index => $cat) {
            $created = KnowledgeCategory::create(array_merge($cat, [
                'tenant_id' => $tenantId,
                'sort_order' => $index,
                'is_active' => true,
            ]));

            if ($cat['slug'] === 'getting-started') {
                KnowledgeArticle::create([
                    'tenant_id' => $tenantId,
                    'category_id' => $created->id,
                    'title' => 'Quick Start Guide: Configuring Your Business CRM Workspace',
                    'slug' => 'quick-start-guide-crm-workspace',
                    'excerpt' => 'Step-by-step overview of user onboarding, branch setup, and channel integration.',
                    'content' => 'Welcome to Enterprise CRM! Step 1: Set up your business profile under Organization Settings. Step 2: Configure roles and permissions in RBAC. Step 3: Connect official WhatsApp or email channels.',
                    'tags' => ['onboarding', 'setup', 'admin'],
                    'is_published' => true,
                    'visibility' => 'public',
                    'version' => '1.0',
                ]);
            }
        }
    }

    /**
     * Search and list knowledge articles with filters.
     */
    public function getArticles(
        int $tenantId,
        ?string $search = null,
        ?int $categoryId = null,
        ?string $visibility = null,
        bool $onlyPublished = true
    ): Collection {
        $query = KnowledgeArticle::where('tenant_id', $tenantId)->with('category');

        if ($onlyPublished) {
            $query->where('is_published', true);
        }
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        if ($visibility) {
            $query->where('visibility', $visibility);
        }
        if ($search) {
            $words = array_filter(explode(' ', trim($search)), fn($w) => strlen(trim($w)) >= 3);
            if (!empty($words)) {
                $query->where(function ($q) use ($words) {
                    foreach ($words as $word) {
                        $w = trim($word);
                        $q->orWhere('title', 'like', "%{$w}%")
                          ->orWhere('content', 'like', "%{$w}%")
                          ->orWhere('excerpt', 'like', "%{$w}%");
                    }
                });
            }
        }

        return $query->orderBy('helpful_count', 'desc')->get();
    }

    /**
     * Get single article and increment view counter.
     */
    public function getArticle(int $tenantId, int $id): KnowledgeArticle
    {
        $article = KnowledgeArticle::where('tenant_id', $tenantId)->with('category')->findOrFail($id);
        $article->increment('view_count');

        return $article;
    }

    /**
     * Create knowledge article.
     */
    public function createArticle(int $tenantId, array $data, ?User $user = null): KnowledgeArticle
    {
        $slug = Str::slug($data['title']) . '-' . Str::random(4);

        return KnowledgeArticle::create([
            'tenant_id' => $tenantId,
            'category_id' => $data['category_id'] ?? null,
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $data['content'],
            'excerpt' => $data['excerpt'] ?? Str::limit(strip_tags($data['content']), 150),
            'tags' => $data['tags'] ?? [],
            'is_published' => $data['is_published'] ?? true,
            'visibility' => $data['visibility'] ?? 'public',
            'version' => $data['version'] ?? '1.0',
            'author_user_id' => $user?->id,
            'approved_by_user_id' => $user?->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Vote on article helpfulness.
     */
    public function voteHelpfulness(int $tenantId, int $id, bool $helpful): KnowledgeArticle
    {
        $article = KnowledgeArticle::where('tenant_id', $tenantId)->findOrFail($id);
        if ($helpful) {
            $article->increment('helpful_count');
        } else {
            $article->increment('not_helpful_count');
        }

        return $article;
    }

    /**
     * Suggest AI response grounded in approved Knowledge Base articles.
     */
    public function suggestAiAnswer(int $tenantId, string $query): array
    {
        $articles = $this->getArticles($tenantId, $query, null, null, true);

        if ($articles->isEmpty()) {
            return [
                'suggested_answer' => "No exact knowledge base match found for query. Please escalate to a support specialist.",
                'confidence_score' => 0.30,
                'referenced_articles' => [],
            ];
        }

        $top = $articles->first();
        $summary = "Based on our knowledge base article '{$top->title}': " .
            ($top->excerpt ?: Str::limit($top->content, 200));

        return [
            'suggested_answer' => $summary,
            'confidence_score' => 0.92,
            'referenced_articles' => $articles->take(3)->map(fn($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'slug' => $a->slug,
            ]),
        ];
    }
}
