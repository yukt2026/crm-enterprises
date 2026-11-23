<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\WhatsApp\Models\WhatsAppAiProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppAiProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $providers = WhatsAppAiProvider::where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        })->get();

        // Securely mask API Keys
        $safeProviders = $providers->map(function ($p) {
            $key = $p->api_key;
            $maskedKey = null;
            if ($key) {
                $len = strlen($key);
                $maskedKey = $len > 8 ? substr($key, 0, 4) . '****' . substr($key, -4) : '********';
            }

            return [
                'id' => $p->id,
                'provider' => $p->provider,
                'name' => $p->name,
                'api_endpoint' => $p->api_endpoint,
                'default_model' => $p->default_model,
                'available_models' => $p->available_models ?: ['gemini-1.5-flash', 'gemini-1.5-pro', 'gpt-4o', 'gpt-4o-mini'],
                'is_active' => $p->is_active,
                'priority' => $p->priority,
                'monthly_budget_usd' => $p->monthly_budget_usd,
                'spent_usd_this_month' => $p->spent_usd_this_month,
                'timeout_seconds' => $p->timeout_seconds,
                'max_retries' => $p->max_retries,
                'has_configured_key' => !empty($key),
                'masked_key' => $maskedKey,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $safeProviders,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $validated = $request->validate([
            'provider' => 'required|in:gemini,openai,anthropic,custom',
            'name' => 'required|string|max:128',
            'api_key' => 'nullable|string',
            'api_endpoint' => 'nullable|string|max:255',
            'default_model' => 'required|string|max:128',
            'available_models' => 'nullable|array',
            'is_active' => 'boolean',
            'monthly_budget_usd' => 'nullable|numeric|min:0',
            'timeout_seconds' => 'nullable|integer|min:5|max:120',
            'max_retries' => 'nullable|integer|min:0|max:5',
        ]);

        $provider = WhatsAppAiProvider::where('tenant_id', $tenantId)
            ->where('provider', $validated['provider'])
            ->first();

        if ($provider) {
            if (empty($validated['api_key'])) {
                unset($validated['api_key']); // Don't wipe existing key if omitted
            }
            $provider->update($validated);
        } else {
            $provider = WhatsAppAiProvider::create(array_merge($validated, [
                'tenant_id' => $tenantId,
            ]));
        }

        return response()->json([
            'success' => true,
            'message' => 'AI Provider configuration updated securely.',
        ]);
    }
}
