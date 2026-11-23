<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\PlatformAdmin\Services\IndustryTemplateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IndustryTemplateController extends Controller
{
    public function __construct(
        protected IndustryTemplateService $templateService
    ) {}

    /**
     * List All 12 Available Industry Starter Templates
     */
    public function index(): JsonResponse
    {
        $templates = $this->templateService->getAvailableTemplates();

        return response()->json([
            'success' => true,
            'data' => $templates,
            'total' => count($templates),
        ]);
    }

    /**
     * Show Single Industry Starter Template Details
     */
    public function show(string $code): JsonResponse
    {
        $template = $this->templateService->getTemplateByCode($code);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => "Industry template [{$code}] not found.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Apply Industry Template to Current Organization
     */
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'template_code' => 'required|string',
            'customizations' => 'nullable|array',
        ]);

        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No active tenant workspace found.',
            ], 400);
        }

        try {
            $applied = $this->templateService->applyTemplateToTenant(
                $tenant,
                $request->template_code,
                $request->input('customizations', [])
            );

            return response()->json([
                'success' => true,
                'data' => $applied,
                'message' => "Successfully applied industry template [{$applied->template->name}] to organization.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get Current Active Template for Organization
     */
    public function current(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No active tenant workspace found.',
            ], 400);
        }

        $current = $this->templateService->getTenantTemplate($tenant);

        return response()->json([
            'success' => true,
            'data' => $current,
        ]);
    }
}
