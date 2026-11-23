<?php

namespace App\Domains\WhatsApp\AI\Providers;

interface AiProviderInterface
{
    /**
     * Generate LLM completion with optional tool definitions.
     *
     * @param array $messages [{role: 'system'|'user'|'assistant'|'tool', content: string, name?: string}]
     * @param array $tools Function/tool calling definitions
     * @param array $options [model, temperature, max_tokens, timeout]
     * @return array [
     *   'content' => ?string,
     *   'tool_calls' => array, // [{name, arguments: array}]
     *   'prompt_tokens' => int,
     *   'completion_tokens' => int,
     *   'total_tokens' => int,
     *   'provider' => string,
     *   'model' => string
     * ]
     */
    public function generateResponse(array $messages, array $tools = [], array $options = []): array;

    /**
     * Generate numerical embedding vector for text chunk.
     *
     * @param string $text
     * @return array<float> List of vector float coordinates
     */
    public function generateEmbedding(string $text): array;

    public function getName(): string;

    public function isAvailable(): bool;
}
