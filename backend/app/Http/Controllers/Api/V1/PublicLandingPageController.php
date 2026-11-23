<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\LandingPages\Models\CustomDomain;
use App\Domains\LandingPages\Models\Form;
use App\Domains\LandingPages\Models\LandingPage;
use App\Domains\LandingPages\Services\FormIngestionService;
use App\Domains\LandingPages\Services\LandingPageAnalyticsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicLandingPageController extends Controller
{
    public function __construct(
        protected FormIngestionService $formIngestionService,
        protected LandingPageAnalyticsService $analyticsService
    ) {}

    /**
     * Render / Fetch a published landing page by slug or custom domain host.
     */
    public function getPage(Request $request, string $slug): JsonResponse
    {
        $host = $request->getHost();

        // Check if request matches a verified custom domain
        $customDomain = CustomDomain::where('domain', $host)
            ->where('verification_status', 'verified')
            ->first();

        $query = LandingPage::where('status', 'published');

        if ($customDomain) {
            $query->where('tenant_id', $customDomain->tenant_id);
        }

        $page = $query->where('slug', $slug)->first();

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Landing page not found or unpublished.',
            ], 404);
        }

        // Detect device
        $userAgent = $request->userAgent() ?? '';
        $deviceType = 'desktop';
        if (preg_match('/(mobile|android|iphone|ipod)/i', $userAgent)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/(tablet|ipad)/i', $userAgent)) {
            $deviceType = 'tablet';
        }

        $utmData = [
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
        ];

        // Track page view telemetry
        $this->analyticsService->recordEvent(
            $page,
            'page_view',
            $deviceType,
            $request->header('referer'),
            $utmData
        );

        // Check if there are embedded forms to load schemas for
        $embeddedForms = [];
        $sections = $page->sections ?? [];
        foreach ($sections as $section) {
            if (($section['type'] ?? '') === 'form_embed' && !empty($section['form_slug'])) {
                $form = Form::where('tenant_id', $page->tenant_id)
                    ->where('slug', $section['form_slug'])
                    ->where('status', 'active')
                    ->first(['id', 'name', 'slug', 'title', 'description', 'schema', 'settings']);
                if ($form) {
                    $embeddedForms[$section['form_slug']] = $form;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'title' => $page->title,
                'description' => $page->description,
                'branding' => $page->branding,
                'seo_metadata' => $page->seo_metadata,
                'sections' => $page->sections,
                'settings' => $page->settings,
                'published_at' => $page->published_at,
                'embedded_forms' => $embeddedForms,
            ],
        ]);
    }

    /**
     * Track a call-to-action click on a landing page.
     */
    public function trackCtaClick(Request $request, string $slug): JsonResponse
    {
        $page = LandingPage::where('slug', $slug)->where('status', 'published')->first();
        if (!$page) {
            return response()->json(['success' => false, 'message' => 'Page not found'], 404);
        }

        $this->analyticsService->recordEvent(
            $page,
            'cta_click',
            $request->input('device_type', 'desktop'),
            $request->header('referer'),
            [
                'utm_source' => $request->input('utm_source'),
                'utm_medium' => $request->input('utm_medium'),
                'utm_campaign' => $request->input('utm_campaign'),
            ],
            [
                'cta_text' => $request->input('cta_text'),
                'target' => $request->input('cta_target'),
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Public Form Submission endpoint.
     */
    public function submitForm(Request $request, string $slug): JsonResponse
    {
        $form = Form::where('slug', $slug)->where('status', 'active')->first();
        if (!$form) {
            return response()->json(['success' => false, 'message' => 'Form not found or inactive.'], 404);
        }

        $landingPage = null;
        if ($request->filled('landing_page_slug')) {
            $landingPage = LandingPage::where('tenant_id', $form->tenant_id)
                ->where('slug', $request->input('landing_page_slug'))
                ->first();
        }

        $result = $this->formIngestionService->ingest(
            $form,
            $request->all(),
            $landingPage,
            $request->ip(),
            $request->userAgent(),
            $request->header('referer')
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
