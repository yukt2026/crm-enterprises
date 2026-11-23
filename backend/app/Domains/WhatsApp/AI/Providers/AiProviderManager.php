<?php

namespace App\Domains\WhatsApp\AI\Providers;

use App\Domains\WhatsApp\Models\WhatsAppAiAgent;
use App\Domains\WhatsApp\Models\WhatsAppAiProvider;
use Illuminate\Support\Facades\Log;

class AiProviderManager
{
    /**
     * Resolve provider instance for tenant & model specifications.
     */
    public function resolveProvider(string $providerName = 'gemini', ?int $tenantId = null): AiProviderInterface
    {
        // Check for tenant-configured provider in DB
        $dbProvider = null;
        if ($tenantId) {
            $dbProvider = WhatsAppAiProvider::where('tenant_id', $tenantId)
                ->where('provider', $providerName)
                ->where('is_active', true)
                ->first();
        }

        if (!$dbProvider) {
            $dbProvider = WhatsAppAiProvider::whereNull('tenant_id')
                ->where('provider', $providerName)
                ->where('is_active', true)
                ->first();
        }

        $apiKey = $dbProvider?->api_key;
        $timeout = $dbProvider?->timeout_seconds ?? 25;
        $maxRetries = $dbProvider?->max_retries ?? 2;

        if ($providerName === 'openai' || $providerName === 'custom') {
            $endpoint = $dbProvider?->api_endpoint ?: 'https://api.openai.com/v1';
            $defaultModel = $dbProvider?->default_model ?: 'gpt-4o-mini';
            return new OpenAiCompatibleProvider($apiKey, $endpoint, $defaultModel, $timeout, $maxRetries);
        }

        // Default: Gemini
        $defaultModel = $dbProvider?->default_model ?: 'gemini-1.5-flash';
        return new GeminiProvider($apiKey, $defaultModel, $timeout, $maxRetries);
    }

    /**
     * Execute completion with safety fallbacks and token budget verification.
     */
    public function executeWithFallback(
        WhatsAppAiAgent $agent,
        array $messages,
        array $tools = []
    ): array {
        // Enforce monthly token budget cap
        if (!$agent->hasBudgetRemaining()) {
            return [
                'content' => "Our AI assistant is temporarily at maximum capacity. " . ($agent->fallback_message ?: "Connecting you with our team shortly."),
                'tool_calls' => [
                    [
                        'name' => 'escalate_to_human',
                        'arguments' => ['reason' => 'Monthly AI token budget exceeded.']
                    ]
                ],
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'estimated_cost_usd' => 0.0,
                'provider' => $agent->model_provider,
                'model' => $agent->model_name,
                'status' => 'handover_triggered',
            ];
        }

        $primaryProviderName = $agent->model_provider ?: 'gemini';
        $provider = $this->resolveProvider($primaryProviderName, $agent->tenant_id);

        $options = [
            'model' => $agent->model_name,
            'temperature' => $agent->temperature,
            'max_tokens' => $agent->max_tokens,
        ];

        try {
            $result = $provider->generateResponse($messages, $tools, $options);
            $cost = $this->calculateCostUsd($result['provider'], $result['model'], $result['prompt_tokens'], $result['completion_tokens']);
            $result['estimated_cost_usd'] = $cost;
            $result['status'] = 'success';
            return $result;
        } catch (\Exception $e) {
            Log::warning("Primary AI Provider [{$primaryProviderName}] failed: " . $e->getMessage() . ". Attempting safe fallback...");

            // Safe fallback to secondary provider (e.g. Gemini if OpenAI failed, or vice versa)
            $fallbackName = ($primaryProviderName === 'gemini') ? 'openai' : 'gemini';
            try {
                $fallbackProvider = $this->resolveProvider($fallbackName, $agent->tenant_id);
                $options['model'] = ($fallbackName === 'gemini') ? 'gemini-1.5-flash' : 'gpt-4o-mini';
                $result = $fallbackProvider->generateResponse($messages, $tools, $options);
                $cost = $this->calculateCostUsd($result['provider'], $result['model'], $result['prompt_tokens'], $result['completion_tokens']);
                $result['estimated_cost_usd'] = $cost;
                $result['status'] = 'fallback_used';
                return $result;
            } catch (\Exception $fallbackErr) {
                Log::error("AI Fallback provider also failed: " . $fallbackErr->getMessage());

                return [
                    'content' => $agent->fallback_message ?: "I'm having trouble processing this right now. Let me connect you with a team member.",
                    'tool_calls' => [
                        [
                            'name' => 'escalate_to_human',
                            'arguments' => ['reason' => 'AI providers connection failure.']
                        ]
                    ],
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0,
                    'estimated_cost_usd' => 0.0,
                    'provider' => $primaryProviderName,
                    'model' => $agent->model_name,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * Compute approximate USD cost per token usage.
     */
    public function calculateCostUsd(string $provider, string $model, int $promptTokens, int $completionTokens): float
    {
        // Pricing per million tokens:
        // Gemini 1.5 Flash: $0.075 / 1M prompt, $0.30 / 1M completion
        // GPT-4o mini: $0.15 / 1M prompt, $0.60 / 1M completion
        $promptRate = str_contains($model, 'flash') ? 0.075 / 1000000 : 0.15 / 1000000;
        $completionRate = str_contains($model, 'flash') ? 0.30 / 1000000 : 0.60 / 1000000;

        $cost = ($promptTokens * $promptRate) + ($completionTokens * $completionRate);
        return round($cost, 6);
    }
}
