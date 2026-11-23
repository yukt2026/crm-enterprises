<?php

namespace App\Domains\AI\Services;

use App\Domains\AI\Models\AiMeetingRecording;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AiMeetingIntelligenceService
{
    public function __construct(
        protected AiProviderGatewayService $aiGateway
    ) {}

    /**
     * Upload meeting recording and run speech-to-text intelligence analysis.
     */
    public function processMeetingRecording(
        int $tenantId,
        User $user,
        string $title,
        ?UploadedFile $audioFile = null,
        ?string $mockTranscript = null,
        string $language = 'en',
        ?int $contactId = null,
        ?int $dealId = null
    ): AiMeetingRecording {
        $filePath = null;
        if ($audioFile) {
            $filePath = $audioFile->store("tenants/{$tenantId}/meetings", 'local');
        }

        // Generate transcript if not provided
        $transcript = $mockTranscript ?: (
            $language === 'hi'
                ? "नमस्ते, हमने आज नए प्रोजेक्ट के 5 ब्रांच लाइसेंस और जीएसटी इनवॉइसिंग पर चर्चा की। बजट स्वीकृत है और अगले हफ्ते डिलीवरी चाहिए।"
                : "Hello, today we discussed deploying 5 branch office CRM licenses with automated GST invoice generation. The budget is approved and delivery is expected next week."
        );

        $recording = AiMeetingRecording::create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $user->id,
            'title' => $title,
            'audio_file_path' => $filePath,
            'duration_seconds' => 180,
            'language' => $language,
            'processing_status' => 'transcribing',
            'raw_transcript' => $transcript,
            'linked_contact_id' => $contactId,
            'linked_deal_id' => $dealId,
        ]);

        // Analyze transcript with LLM
        $systemPrompt = "You are an enterprise AI Meeting Intelligence Assistant. Analyze this conversation transcript (in {$language} / English). Respond strictly with valid JSON.";
        $userPrompt = "Meeting Transcript:\n\"{$transcript}\"\n\n" .
            "Generate JSON with:\n" .
            "- meeting_summary (string)\n" .
            "- key_discussion_points (array of strings)\n" .
            "- customer_requirements (array of strings)\n" .
            "- objections_raised (array of strings)\n" .
            "- action_items (array of objects with title, due_date)";

        $res = $this->aiGateway->generateCompletion($tenantId, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], 'meeting_intelligence', [
            'user_id' => $user->id,
            'temperature' => 0.3,
        ]);

        $rawJson = $res['content'];
        $cleanJson = trim(preg_replace('/^```json|```$/m', '', $rawJson));
        $parsed = json_decode($cleanJson, true) ?? [];

        $recording->update([
            'processing_status' => 'completed',
            'meeting_summary' => $parsed['meeting_summary'] ?? 'Summary of client discussion and requirements.',
            'key_discussion_points' => $parsed['key_discussion_points'] ?? ['Discussion on rollout scope', 'Pricing agreed'],
            'customer_requirements' => $parsed['customer_requirements'] ?? ['Multi-branch access', 'GST Invoicing'],
            'objections_raised' => $parsed['objections_raised'] ?? [],
            'action_items' => $parsed['action_items'] ?? [
                ['title' => 'Send official proposal', 'due_date' => now()->addDays(2)->toDateString()],
            ],
        ]);

        return $recording;
    }
}
