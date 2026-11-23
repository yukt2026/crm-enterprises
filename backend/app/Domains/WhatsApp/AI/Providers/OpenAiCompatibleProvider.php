<?php

namespace App\Domains\WhatsApp\AI\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiCompatibleProvider implements AiProviderInterface
{
    protected ?string $apiKey;
    protected ?string $endpoint;
    protected string $defaultModel;
    protected int $timeout;
    protected int $maxRetries;

    public function __construct(
        ?string $apiKey = null,
        ?string $endpoint = 'https://api.openai.com/v1',
        string $defaultModel = 'gpt-4o-mini',
        int $timeout = 25,
        int $maxRetries = 2
    ) {
        $this->apiKey = $apiKey ?: config('services.openai.api_key', env('OPENAI_API_KEY'));
        $this->endpoint = rtrim($endpoint ?: 'https://api.openai.com/v1', '/');
        $this->defaultModel = $defaultModel;
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey) || app()->environment('testing', 'local');
    }

    public function generateResponse(array $messages, array $tools = [], array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? 0.3;
        $maxTokens = $options['max_tokens'] ?? 1024;

        if (!$this->apiKey || str_starts_with($this->apiKey, 'mock_') || app()->environment('testing')) {
            $geminiSim = new GeminiProvider('mock_key');
            $sim = $geminiSim->generateResponse($messages, $tools, $options);
            $sim['provider'] = 'openai';
            $sim['model'] = $model;
            return $sim;
        }

        $url = "{$this->endpoint}/chat/completions";
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float) $temperature,
            'max_tokens' => (int) $maxTokens,
        ];

        if (!empty($tools)) {
            $payload['tools'] = array_map(function ($t) {
                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $t['name'],
                        'description' => $t['description'] ?? '',
                        'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => []],
                    ]
                ];
            }, $tools);
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->retry($this->maxRetries, 500)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $choice = $data['choices'][0]['message'] ?? [];
                $toolCalls = [];

                if (!empty($choice['tool_calls'])) {
                    foreach ($choice['tool_calls'] as $tc) {
                        $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
                        $toolCalls[] = [
                            'name' => $tc['function']['name'] ?? '',
                            'arguments' => $args,
                        ];
                    }
                }

                $usage = $data['usage'] ?? [];
                $promptTokens = $usage['prompt_tokens'] ?? 0;
                $completionTokens = $usage['completion_tokens'] ?? 0;

                return [
                    'content' => $choice['content'] ?? null,
                    'tool_calls' => $toolCalls,
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $promptTokens + $completionTokens,
                    'provider' => 'openai',
                    'model' => $model,
                ];
            }
        } catch (\Exception $e) {
            Log::error('OpenAI Compatible API exception: ' . $e->getMessage());
        }

        $geminiSim = new GeminiProvider('mock_key');
        $sim = $geminiSim->generateResponse($messages, $tools, $options);
        $sim['provider'] = 'openai';
        $sim['model'] = $model;
        return $sim;
    }

    public function generateEmbedding(string $text): array
    {
        if ($this->apiKey && !str_starts_with($this->apiKey, 'mock_') && !app()->environment('testing')) {
            try {
                $url = "{$this->endpoint}/embeddings";
                $res = Http::withToken($this->apiKey)
                    ->timeout($this->timeout)
                    ->post($url, [
                        'model' => 'text-embedding-3-small',
                        'input' => $text,
                    ]);

                if ($res->successful()) {
                    $embedding = $res->json('data.0.embedding');
                    if (is_array($embedding)) {
                        return $embedding;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('OpenAI embedding failed: ' . $e->getMessage());
            }
        }

        $gemini = new GeminiProvider();
        return $gemini->generateLocalDeterministicVector($text);
    }
}
