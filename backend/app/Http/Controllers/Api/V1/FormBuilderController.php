<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\LandingPages\Models\Form;
use App\Domains\LandingPages\Models\FormSubmission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormBuilderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $forms = Form::where('tenant_id', $tenantId)
            ->withCount('submissions')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $forms,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'schema' => 'required|array',
            'settings' => 'nullable|array',
        ]);

        $tenantId = $request->user()->current_tenant_id;
        $slug = Str::slug($validated['slug'] ?? $validated['name']);

        $baseSlug = $slug;
        $counter = 1;
        while (Form::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $form = Form::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'slug' => $slug,
            'title' => $validated['title'] ?? $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => 'active',
            'schema' => $validated['schema'],
            'settings' => $validated['settings'] ?? [
                'submit_button_text' => 'Submit Inquiry',
                'success_type' => 'message',
                'success_message' => 'Thank you! We will get in touch shortly.',
                'require_consent' => true,
                'consent_text' => 'I consent to receiving business communications from this company.',
                'spam_protection' => [
                    'honeypot_enabled' => true,
                    'min_time_seconds' => 2,
                    'rate_limit_per_minute' => 15,
                ],
            ],
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Form created successfully.',
            'data' => $form,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $form = Form::where('tenant_id', $tenantId)->withCount('submissions')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $form,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $form = Form::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
            'schema' => 'sometimes|required|array',
            'settings' => 'nullable|array',
        ]);

        if (isset($validated['slug']) && $validated['slug'] !== $form->slug) {
            $newSlug = Str::slug($validated['slug']);
            $conflict = Form::where('tenant_id', $tenantId)
                ->where('slug', $newSlug)
                ->where('id', '!=', $form->id)
                ->exists();
            if ($conflict) {
                return response()->json(['success' => false, 'message' => 'The slug is already taken.'], 422);
            }
            $validated['slug'] = $newSlug;
        }

        $form->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Form updated successfully.',
            'data' => $form->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $form = Form::where('tenant_id', $tenantId)->findOrFail($id);
        $form->delete();

        return response()->json([
            'success' => true,
            'message' => 'Form deleted successfully.',
        ]);
    }

    public function submissions(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $form = Form::where('tenant_id', $tenantId)->findOrFail($id);

        $query = FormSubmission::where('tenant_id', $tenantId)
            ->where('form_id', $form->id)
            ->with(['lead:id,name,email,phone,status', 'landingPage:id,name,slug'])
            ->orderByDesc('created_at');

        $submissions = $query->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $submissions->items(),
            'pagination' => [
                'current_page' => $submissions->currentPage(),
                'total' => $submissions->total(),
                'per_page' => $submissions->perPage(),
                'last_page' => $submissions->lastPage(),
            ],
        ]);
    }
}
