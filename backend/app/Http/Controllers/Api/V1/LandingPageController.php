<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\LandingPages\Models\LandingPage;
use App\Domains\LandingPages\Models\LandingPageTemplate;
use App\Domains\LandingPages\Services\LandingPagePublishingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LandingPageController extends Controller
{
    public function __construct(
        protected LandingPagePublishingService $publishingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $query = LandingPage::where('tenant_id', $tenantId)
            ->with(['customDomain', 'createdByUser:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%");
            });
        }

        $pages = $query->orderByDesc('updated_at')->get();

        return response()->json([
            'success' => true,
            'data' => $pages,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'custom_domain_id' => 'nullable|exists:custom_domains,id',
            'branding' => 'nullable|array',
            'seo_metadata' => 'nullable|array',
            'sections' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        $tenantId = $request->user()->current_tenant_id;
        $slug = Str::slug($validated['slug'] ?? $validated['name']);

        // Ensure unique slug within tenant
        $baseSlug = $slug;
        $counter = 1;
        while (LandingPage::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $page = LandingPage::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'slug' => $slug,
            'title' => $validated['title'] ?? $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => 'draft',
            'custom_domain_id' => $validated['custom_domain_id'] ?? null,
            'branding' => $validated['branding'] ?? [
                'primary_color' => '#4f46e5',
                'secondary_color' => '#06b6d4',
                'font_family' => 'Inter, sans-serif',
            ],
            'seo_metadata' => $validated['seo_metadata'] ?? [
                'meta_title' => $validated['title'] ?? $validated['name'],
                'meta_description' => $validated['description'] ?? '',
            ],
            'sections' => $validated['sections'] ?? [
                [
                    'id' => 'sec_hero_default',
                    'type' => 'hero',
                    'headline' => 'Welcome to Our New Experience',
                    'subheadline' => 'Capture attention with clean typography and conversion-optimized structure.',
                    'cta_text' => 'Get Started',
                    'is_visible' => true,
                ],
            ],
            'settings' => $validated['settings'] ?? [],
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Landing page created successfully.',
            'data' => $page->load('customDomain'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $page = LandingPage::where('tenant_id', $tenantId)
            ->with(['customDomain', 'revisions', 'createdByUser:id,name'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $page,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $page = LandingPage::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'custom_domain_id' => 'nullable|exists:custom_domains,id',
            'branding' => 'nullable|array',
            'seo_metadata' => 'nullable|array',
            'sections' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        if (isset($validated['slug']) && $validated['slug'] !== $page->slug) {
            $newSlug = Str::slug($validated['slug']);
            $conflict = LandingPage::where('tenant_id', $tenantId)
                ->where('slug', $newSlug)
                ->where('id', '!=', $page->id)
                ->exists();
            if ($conflict) {
                return response()->json(['success' => false, 'message' => 'The slug is already taken.'], 422);
            }
            $validated['slug'] = $newSlug;
        }

        $page->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Landing page updated successfully.',
            'data' => $page->fresh(['customDomain', 'revisions']),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $page = LandingPage::where('tenant_id', $tenantId)->findOrFail($id);
        $page->delete();

        return response()->json([
            'success' => true,
            'message' => 'Landing page deleted successfully.',
        ]);
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $page = LandingPage::where('tenant_id', $tenantId)->findOrFail($id);

        $changelog = $request->input('changelog');
        $publishedPage = $this->publishingService->publish($page, $changelog, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Landing page published successfully.',
            'data' => $publishedPage,
        ]);
    }

    public function unpublish(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $page = LandingPage::where('tenant_id', $tenantId)->findOrFail($id);

        $unpublishedPage = $this->publishingService->unpublish($page);

        return response()->json([
            'success' => true,
            'message' => 'Landing page unpublished.',
            'data' => $unpublishedPage,
        ]);
    }

    public function rollback(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $page = LandingPage::where('tenant_id', $tenantId)->findOrFail($id);

        $targetVersion = (int) $request->validate([
            'version' => 'required|integer',
        ])['version'];

        $rolledBackPage = $this->publishingService->rollback($page, $targetVersion, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => "Landing page rolled back to version {$targetVersion}.",
            'data' => $rolledBackPage,
        ]);
    }

    public function templates(): JsonResponse
    {
        $templates = LandingPageTemplate::all();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function createFromTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:landing_page_templates,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
        ]);

        $tenantId = $request->user()->current_tenant_id;
        $template = LandingPageTemplate::findOrFail($validated['template_id']);

        $slug = Str::slug($validated['slug'] ?? $validated['name']);
        $baseSlug = $slug;
        $counter = 1;
        while (LandingPage::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $page = LandingPage::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'slug' => $slug,
            'title' => $validated['name'],
            'description' => $template->description,
            'status' => 'draft',
            'branding' => $template->branding,
            'seo_metadata' => [
                'meta_title' => $validated['name'],
                'meta_description' => $template->description,
            ],
            'sections' => $template->sections,
            'settings' => [],
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Landing page created from template.',
            'data' => $page,
        ], 201);
    }
}
