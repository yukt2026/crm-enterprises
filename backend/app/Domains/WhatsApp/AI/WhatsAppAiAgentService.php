<?php

namespace App\Domains\WhatsApp\AI;

use App\Domains\WhatsApp\AI\Knowledge\KnowledgeBaseService;
use App\Domains\WhatsApp\AI\Providers\AiProviderManager;
use App\Domains\WhatsApp\AI\Tools\CrmToolRegistry;
use App\Domains\WhatsApp\Models\WhatsAppAiAgent;
use App\Domains\WhatsApp\Models\WhatsAppAiExecutionLog;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use App\Domains\WhatsApp\Models\WhatsAppOptOut;
use App\Domains\WhatsApp\Services\WhatsAppMessagingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class WhatsAppAiAgentService
{
    protected AiProviderManager $providerManager;
    protected KnowledgeBaseService $knowledgeService;
    protected CrmToolRegistry $toolRegistry;
    protected WhatsAppMessagingService $messagingService;

    public function __construct(
        AiProviderManager $providerManager,
        KnowledgeBaseService $knowledgeService,
        CrmToolRegistry $toolRegistry,
        WhatsAppMessagingService $messagingService
    ) {
        $this->providerManager = $providerManager;
        $this->knowledgeService = $knowledgeService;
        $this->toolRegistry = $toolRegistry;
        $this->messagingService = $messagingService;
    }

    /**
     * Process an incoming customer message with AI Agent if enabled.
     */
    public function processInboundMessage(WhatsAppConversation $conversation, WhatsAppMessage $message): ?WhatsAppAiExecutionLog
    {
        // 1. Check if AI handling is active for this conversation
        if (!$conversation->is_ai_handled) {
            return null;
        }

        $tenantId = $conversation->tenant_id;

        // 2. Opt-out safety check
        $isOptedOut = WhatsAppOptOut::where('tenant_id', $tenantId)
            ->where('phone_number', $conversation->phone_number)
            ->exists();
        if ($isOptedOut) {
            return null;
        }

        // 3. Resolve active AI Agent for phone number or default for tenant
        $agent = WhatsAppAiAgent::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($q) use ($conversation) {
                $q->where('whatsapp_phone_number_id', $conversation->whatsapp_phone_number_id)
                  ->orWhereNull('whatsapp_phone_number_id');
            })
            ->first();

        if (!$agent) {
            return null;
        }

        $userText = $message->content ?: '';

        // 4. Retrieve conversation history
        $recentMessages = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->where('id', '<=', $message->id)
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get()
            ->reverse();

        $history = [];
        foreach ($recentMessages as $m) {
            $history[] = [
                'role' => ($m->direction === 'inbound') ? 'user' : 'assistant',
                'content' => $m->content ?: ''
            ];
        }

        // 5. Execute AI Generation & Tool loop
        $startTime = microtime(true);
        $result = $this->runAgentPipeline($agent, $userText, $history, $conversation);
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        // 6. Record execution log
        $log = WhatsAppAiExecutionLog::create([
            'tenant_id' => $tenantId,
            'agent_id' => $agent->id,
            'conversation_id' => $conversation->id,
            'provider' => $result['provider'] ?? $agent->model_provider,
            'model' => $result['model'] ?? $agent->model_name,
            'prompt_tokens' => $result['prompt_tokens'] ?? 0,
            'completion_tokens' => $result['completion_tokens'] ?? 0,
            'total_tokens' => $result['total_tokens'] ?? 0,
            'estimated_cost_usd' => $result['estimated_cost_usd'] ?? 0.0,
            'latency_ms' => $latencyMs,
            'tool_calls' => $result['tool_executions'] ?? [],
            'knowledge_citations' => $result['citations'] ?? [],
            'decision_trace' => $result['trace'] ?? [],
            'user_query' => $userText,
            'ai_response' => $result['reply'] ?? null,
            'status' => $result['status'] ?? 'success',
            'error_message' => $result['error_message'] ?? null,
        ]);

        // 7. Update agent token usage counters
        $agent->increment('used_tokens_this_month', $result['total_tokens'] ?? 0);

        // 8. If AI produced a customer reply and session window is active, dispatch outbound message
        if (!empty($result['reply']) && $conversation->isWindowActive()) {
            try {
                $this->messagingService->sendTextMessage(
                    $conversation->tenant_id,
                    $conversation->id,
                    $result['reply'],
                    null,
                    'ai'
                );
            } catch (\Exception $e) {
                Log::error("Failed to send AI response message on WhatsApp: " . $e->getMessage());
            }
        }

        return $log;
    }

    /**
     * Run the Agent pipeline (RAG + Prompt injection check + LLM + Tools).
     */
    public function runAgentPipeline(
        WhatsAppAiAgent $agent,
        string $userQuery,
        array $conversationHistory = [],
        ?WhatsAppConversation $conversation = null
    ): array {
        $tenantId = $agent->tenant_id;
        $trace = [
            'sanitized_query' => $userQuery,
            'language_detected' => $this->detectLanguage($userQuery),
            'prompt_injection_flag' => false,
        ];

        // Prompt injection defense
        $lower = mb_strtolower($userQuery);
        if (str_contains($lower, 'ignore all previous') || str_contains($lower, 'system prompt leak') || str_contains($lower, 'give 100% discount')) {
            $trace['prompt_injection_flag'] = true;
            return [
                'reply' => "I am programmed to assist with verified business inquiries and cannot bypass our standard operational policies.",
                'tool_executions' => [],
                'citations' => [],
                'trace' => $trace,
                'prompt_tokens' => 80,
                'completion_tokens' => 20,
                'total_tokens' => 100,
                'estimated_cost_usd' => 0.000015,
                'provider' => $agent->model_provider,
                'model' => $agent->model_name,
                'status' => 'success',
            ];
        }

        // 1. RAG: Retrieve top knowledge chunks from Knowledge Base
        $knowledgeChunks = $this->knowledgeService->searchKnowledge($tenantId, $userQuery, 3);
        $citations = array_map(function ($c) {
            return [
                'source' => $c['source_citation'],
                'score' => $c['score'],
                'content' => mb_substr($c['content'], 0, 160) . '...'
            ];
        }, $knowledgeChunks);

        // 2. Assemble System Prompt with Knowledge Citations
        $systemPrompt = $agent->system_prompt ?: "You are a professional Enterprise CRM WhatsApp AI Assistant.";
        $systemPrompt .= "\nTone: {$agent->tone}. Primary Language: {$agent->primary_language}.";

        if (!empty($knowledgeChunks)) {
            $systemPrompt .= "\n\n--- APPROVED BUSINESS KNOWLEDGE (CITE ACCURATELY) ---\n";
            foreach ($knowledgeChunks as $idx => $chunk) {
                $systemPrompt .= "[" . ($idx + 1) . "] {$chunk['source_citation']}: {$chunk['content']}\n";
            }
            $systemPrompt .= "--- END KNOWLEDGE ---\nRule: Only state facts from approved knowledge. Do not invent pricing or discounts.";
        }

        // 3. Assemble Messages
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        foreach ($conversationHistory as $h) {
            $messages[] = $h;
        }

        // Ensure user message is at the end
        if (empty($conversationHistory) || end($conversationHistory)['content'] !== $userQuery) {
            $messages[] = ['role' => 'user', 'content' => $userQuery];
        }

        // 4. Resolve allowed tool definitions
        $allowedToolNames = $agent->allowed_tools ?: [
            'find_or_create_lead',
            'update_lead_fields',
            'add_crm_note',
            'create_follow_up_task',
            'get_product_catalog',
            'request_appointment',
            'escalate_to_human'
        ];
        $toolSchemas = $this->toolRegistry->getAvailableToolsSchema($allowedToolNames);

        // 5. Execute LLM Call via Provider Manager
        $llmResponse = $this->providerManager->executeWithFallback($agent, $messages, $toolSchemas);

        $toolExecutions = [];
        $finalReply = $llmResponse['content'] ?? null;

        // 6. Process tool calls
        if (!empty($llmResponse['tool_calls']) && $conversation) {
            foreach ($llmResponse['tool_calls'] as $tc) {
                $toolName = $tc['name'];
                $toolArgs = $tc['arguments'] ?? [];

                $execResult = $this->toolRegistry->executeTool(
                    $toolName,
                    $toolArgs,
                    $conversation,
                    $allowedToolNames
                );

                $toolExecutions[] = [
                    'tool' => $toolName,
                    'arguments' => $toolArgs,
                    'result' => $execResult,
                ];

                if ($toolName === 'escalate_to_human') {
                    $trace['handover_triggered'] = true;
                }
            }
        }

        return [
            'reply' => $finalReply ?: $agent->fallback_message,
            'tool_executions' => $toolExecutions,
            'citations' => $citations,
            'trace' => $trace,
            'prompt_tokens' => $llmResponse['prompt_tokens'] ?? 0,
            'completion_tokens' => $llmResponse['completion_tokens'] ?? 0,
            'total_tokens' => $llmResponse['total_tokens'] ?? 0,
            'estimated_cost_usd' => $llmResponse['estimated_cost_usd'] ?? 0.0,
            'provider' => $llmResponse['provider'] ?? $agent->model_provider,
            'model' => $llmResponse['model'] ?? $agent->model_name,
            'status' => $llmResponse['status'] ?? 'success',
            'error_message' => $llmResponse['error_message'] ?? null,
        ];
    }

    /**
     * Interactive Playground Test Runner (Does not require actual WhatsApp delivery).
     */
    public function simulatePlayground(
        WhatsAppAiAgent $agent,
        string $userQuery,
        array $conversationHistory = []
    ): array {
        $startTime = microtime(true);
        $result = $this->runAgentPipeline($agent, $userQuery, $conversationHistory, null);
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        $result['latency_ms'] = $latencyMs;
        return $result;
    }

    /**
     * Language detection helper (Hindi, Hinglish, English).
     */
    protected function detectLanguage(string $text): string
    {
        $lower = mb_strtolower($text);
        if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
            return 'Hindi (Devanagari)';
        }

        $hinglishKeywords = ['namaste', 'kaise', 'kya', 'chahiye', 'baat', 'karni', 'bhai', 'shukriya', 'theek', 'hai', 'karo', 'mujhe', 'aap'];
        foreach ($hinglishKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return 'Hinglish';
            }
        }

        return 'English';
    }
}
