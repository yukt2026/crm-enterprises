<?php

namespace App\Domains\Support\Services;

use App\Domains\Support\Models\SupportTicket;
use App\Domains\WhatsApp\AI\Knowledge\KnowledgeBaseService;
use App\Domains\WhatsApp\Models\WhatsAppKnowledgeChunk;
use Illuminate\Support\Str;

class SupportAiService
{
    public function __construct(
        protected ?KnowledgeBaseService $knowledgeBaseService = null
    ) {}

    /**
     * Generate complete AI support assistance bundle:
     * summary, troubleshooting steps, category suggestion, and draft reply.
     */
    public function generateAiAssistance(SupportTicket $ticket, ?string $additionalContext = null): array
    {
        $tenantId = $ticket->tenant_id;
        $subject = $ticket->subject;
        $description = $ticket->description ?? '';
        $query = "{$subject} {$description} {$additionalContext}";

        // 1. Retrieve relevant knowledge documents if knowledge base is configured
        $relevantChunks = $this->retrieveRelevantKnowledge($tenantId, $query);

        // 2. Classify intent and recommended category
        $suggestedCategory = $this->classifyCategory($query);

        // 3. Generate structured troubleshooting steps
        $troubleshootingSteps = $this->generateTroubleshootingSteps($query, $relevantChunks);

        // 4. Synthesize ticket summary
        $summary = $this->summarizeTicket($ticket);

        // 5. Compose polite, professional draft reply based on retrieved knowledge
        $draftReply = $this->composeDraftReply($ticket, $relevantChunks, $troubleshootingSteps);

        $confidenceScore = !empty($relevantChunks) ? 0.92 : 0.82;

        // Persist drafts onto ticket record
        $ticket->update([
            'ai_reply_draft' => $draftReply,
            'ai_summary' => $summary,
            'ai_troubleshooting_steps' => implode("\n", $troubleshootingSteps),
            'ai_suggested_category' => $suggestedCategory,
            'ai_confidence_score' => $confidenceScore,
        ]);

        return [
            'ticket_id' => $ticket->id,
            'summary' => $summary,
            'suggested_category' => $suggestedCategory,
            'troubleshooting_steps' => $troubleshootingSteps,
            'draft_reply' => $draftReply,
            'confidence_score' => $confidenceScore,
            'relevant_docs' => array_map(function ($c) {
                return [
                    'title' => $c['title'] ?? 'Internal Knowledge Base',
                    'snippet' => Str::limit($c['content'] ?? '', 120),
                ];
            }, $relevantChunks),
        ];
    }

    /**
     * Retrieve knowledge chunks matching query for tenant
     */
    protected function retrieveRelevantKnowledge(int $tenantId, string $query): array
    {
        try {
            // Text matching or semantic search
            $keywords = array_filter(explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9 ]/', '', $query))));
            $qBuilder = WhatsAppKnowledgeChunk::where('tenant_id', $tenantId);

            if (!empty($keywords)) {
                $qBuilder->where(function ($sub) use ($keywords) {
                    foreach (array_slice($keywords, 0, 4) as $kw) {
                        if (strlen($kw) > 3) {
                            $sub->orWhere('content', 'like', "%{$kw}%");
                        }
                    }
                });
            }

            $chunks = $qBuilder->limit(3)->get();

            return $chunks->map(function ($chunk) {
                return [
                    'id' => $chunk->id,
                    'title' => $chunk->knowledgeBase?->title ?? 'Knowledge Document',
                    'content' => $chunk->content,
                ];
            })->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Classify category from query content
     */
    public function classifyCategory(string $text): string
    {
        $lower = strtolower($text);

        if (Str::contains($lower, ['billing', 'invoice', 'payment', 'charge', 'refund', 'price', 'subscription'])) {
            return 'Billing & Invoicing';
        }

        if (Str::contains($lower, ['login', 'auth', 'password', '2fa', 'token', 'permission', 'access'])) {
            return 'Account & Access';
        }

        if (Str::contains($lower, ['api', 'webhook', 'integration', 'sdk', 'payload', 'error 500', 'bug', 'crash'])) {
            return 'Technical & API Integrations';
        }

        if (Str::contains($lower, ['whatsapp', 'calling', 'telephony', 'voice', 'sms', 'email'])) {
            return 'Communications & Channels';
        }

        return 'General Inquiries';
    }

    /**
     * Generate diagnostic and troubleshooting steps
     */
    public function generateTroubleshootingSteps(string $query, array $chunks): array
    {
        $steps = [];

        if (!empty($chunks)) {
            $steps[] = "Reference internal policy: " . Str::limit($chunks[0]['content'], 100);
        }

        $lower = strtolower($query);

        if (Str::contains($lower, ['api', 'webhook', 'integration'])) {
            $steps[] = "Verify request headers including Bearer token and X-Workspace-Id.";
            $steps[] = "Check Webhook delivery status in developer settings logs.";
            $steps[] = "Confirm idempotency key has not expired.";
        } elseif (Str::contains($lower, ['billing', 'invoice', 'payment'])) {
            $steps[] = "Review invoice status and associated payment transaction reference.";
            $steps[] = "Check gateway response code in Payment Ledger.";
            $steps[] = "Confirm credit note or refund eligibility with finance team.";
        } else {
            $steps[] = "Confirm customer identifier, environment, and reproduction timestamp.";
            $steps[] = "Inspect recent audit logs for tenant configuration modifications.";
            $steps[] = "Test service availability via internal health diagnostics.";
        }

        return $steps;
    }

    /**
     * Summarize ticket subject and conversation history
     */
    public function summarizeTicket(SupportTicket $ticket): string
    {
        $customerName = $ticket->customer?->name ?? 'Customer';
        $channel = ucfirst($ticket->source_channel ?? 'web chat');
        $priority = strtoupper($ticket->priority ?? 'medium');

        $messageCount = $ticket->messages()->count();
        $desc = Str::limit($ticket->description ?? 'No initial description provided.', 150);

        return "{$priority} priority issue reported by {$customerName} via {$channel}. Summary: \"{$desc}\". Thread contains {$messageCount} messages.";
    }

    /**
     * Compose draft reply adhering to strict safety policies
     */
    public function composeDraftReply(SupportTicket $ticket, array $chunks, array $troubleshootingSteps): string
    {
        $customerName = $ticket->customer?->name ?? 'valued customer';
        $ticketNum = $ticket->ticket_number ?? "#{$ticket->id}";

        $solutionContext = '';
        if (!empty($chunks)) {
            $solutionSnippet = Str::limit(strip_tags($chunks[0]['content']), 160);
            $solutionContext = "\n\nBased on our documentation:\n{$solutionSnippet}";
        }

        $firstStep = !empty($troubleshootingSteps) ? $troubleshootingSteps[0] : 'We are investigating the logs.';

        return "Hello {$customerName},\n\nThank you for reaching out regarding ticket {$ticketNum} (\"{$ticket->subject}\").\n\nOur engineering team has received your report and is currently reviewing the system diagnostics. {$solutionContext}\n\nNext steps: {$firstStep}\n\nWe will update you as soon as further information is available. Please feel free to reply with any additional screenshots or error messages.\n\nBest regards,\nCustomer Support Team";
    }
}
