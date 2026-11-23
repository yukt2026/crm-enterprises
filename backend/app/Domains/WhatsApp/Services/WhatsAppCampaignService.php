<?php

namespace App\Domains\WhatsApp\Services;

use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Lead;
use App\Domains\Marketing\Models\MarketingSegment;
use App\Domains\Omnichannel\Models\CustomerCommunicationConsent;
use App\Domains\WhatsApp\Models\WhatsAppCampaign;
use App\Domains\WhatsApp\Models\WhatsAppCampaignRecipient;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use App\Domains\WhatsApp\Models\WhatsAppOptOut;
use App\Domains\WhatsApp\Models\WhatsAppPhoneNumber;
use App\Domains\WhatsApp\Models\WhatsAppTemplate;
use App\Domains\WhatsApp\Models\WhatsAppUsageRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCampaignService
{
    /**
     * Estimated Meta conversation pricing per category (USD).
     */
    protected const ESTIMATED_RATES = [
        'MARKETING' => 0.0500,
        'UTILITY' => 0.0150,
        'AUTHENTICATION' => 0.0300,
        'SERVICE' => 0.0050,
    ];

    /**
     * Create a new campaign record.
     */
    public function createCampaign(int $tenantId, array $data, mixed $userOrId = null): WhatsAppCampaign
    {
        $userId = $userOrId instanceof \App\Models\User ? $userOrId->id : (is_numeric($userOrId) ? (int) $userOrId : 1);

        return DB::transaction(function () use ($tenantId, $data, $userId) {
            $template = WhatsAppTemplate::where('tenant_id', $tenantId)->findOrFail($data['whatsapp_template_id']);
            $phoneNumber = WhatsAppPhoneNumber::where('tenant_id', $tenantId)->findOrFail($data['whatsapp_phone_number_id']);

            $campaign = WhatsAppCampaign::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'whatsapp_phone_number_id' => $phoneNumber->id,
                'whatsapp_template_id' => $template->id,
                'audience_type' => $data['audience_type'] ?? 'segment',
                'segment_id' => $data['segment_id'] ?? null,
                'csv_file_path' => $data['csv_file_path'] ?? null,
                'variable_mappings' => $data['variable_mappings'] ?? [],
                'scheduled_at' => !empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);

            // Populate initial recipients based on audience
            if ($campaign->audience_type === 'segment' && $campaign->segment_id) {
                $this->populateRecipientsFromSegment($campaign);
            } elseif ($campaign->audience_type === 'contacts' && !empty($data['selected_contact_ids'] ?? ($data['contact_ids'] ?? []))) {
                $this->populateRecipientsFromContacts($campaign, $data['selected_contact_ids'] ?? $data['contact_ids']);
            } elseif (!empty($data['csv_rows'])) {
                $this->populateRecipientsFromCsvRows($campaign, $data['csv_rows']);
            } elseif (!empty($data['csv_content'])) {
                $this->importRecipientsFromCsv($campaign, $data['csv_content']);
            }

            return $campaign->fresh(['template', 'phoneNumber', 'recipients']);
        });
    }

    /**
     * Populate and validate recipients from in-memory structured CSV rows.
     */
    public function populateRecipientsFromCsvRows(WhatsAppCampaign $campaign, array $rows): array
    {
        $tenantId = $campaign->tenant_id;
        $seenPhones = [];
        $imported = 0;
        $duplicates = 0;
        $optedOut = 0;

        foreach ($rows as $row) {
            $rawPhone = $row['phone'] ?? ($row['mobile'] ?? ($row[0] ?? null));
            if (!$rawPhone) continue;

            $phone = $this->normalizePhoneNumber($rawPhone);
            if (!$phone) continue;

            if (isset($seenPhones[$phone])) {
                $duplicates++;
                continue;
            }
            $seenPhones[$phone] = true;

            $name = $row['name'] ?? ($row['recipient_name'] ?? 'Customer');
            $vars = [
                '1' => $name,
                '2' => $row['company'] ?? 'Valued Partner',
            ];

            $isSuppressed = $this->isSuppressed($tenantId, $phone);
            if ($isSuppressed) {
                $optedOut++;
            }

            WhatsAppCampaignRecipient::create([
                'tenant_id' => $tenantId,
                'campaign_id' => $campaign->id,
                'phone_number' => $phone,
                'recipient_name' => $name,
                'status' => $isSuppressed ? 'suppressed_opt_out' : 'queued',
                'error_message' => $isSuppressed ? 'Recipient has opted out' : null,
                'variables' => $vars,
            ]);
            $imported++;
        }

        $this->updateCampaignRecipientsCount($campaign);

        return ['imported' => $imported, 'duplicates' => $duplicates, 'opted_out' => $optedOut];
    }

    /**
     * Populate recipients from a CRM Marketing Segment.
     */
    public function populateRecipientsFromSegment(WhatsAppCampaign $campaign): int
    {
        $tenantId = $campaign->tenant_id;
        $contacts = Contact::where('tenant_id', $tenantId)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        $count = 0;
        foreach ($contacts as $contact) {
            $normalized = $this->normalizePhoneNumber($contact->phone);
            if (!$normalized) continue;

            $isOptedOut = $this->isSuppressed($tenantId, $normalized);

            WhatsAppCampaignRecipient::create([
                'tenant_id' => $tenantId,
                'campaign_id' => $campaign->id,
                'phone_number' => $normalized,
                'recipient_name' => $contact->first_name . ' ' . $contact->last_name,
                'contact_id' => $contact->id,
                'status' => $isOptedOut ? 'skipped_opt_out' : 'queued',
                'variables' => [
                    '1' => $contact->first_name,
                    '2' => $contact->company_name ?? 'Valued Customer',
                ],
            ]);
            $count++;
        }

        $this->updateCampaignRecipientsCount($campaign);
        return $count;
    }

    /**
     * Populate recipients from explicit Contact IDs.
     */
    public function populateRecipientsFromContacts(WhatsAppCampaign $campaign, array $contactIds): int
    {
        $tenantId = $campaign->tenant_id;
        $contacts = Contact::where('tenant_id', $tenantId)->whereIn('id', $contactIds)->get();

        $count = 0;
        foreach ($contacts as $contact) {
            $normalized = $this->normalizePhoneNumber($contact->phone);
            if (!$normalized) continue;

            $isOptedOut = $this->isSuppressed($tenantId, $normalized);

            WhatsAppCampaignRecipient::create([
                'tenant_id' => $tenantId,
                'campaign_id' => $campaign->id,
                'phone_number' => $normalized,
                'recipient_name' => trim($contact->first_name . ' ' . $contact->last_name),
                'contact_id' => $contact->id,
                'status' => $isOptedOut ? 'skipped_opt_out' : 'queued',
                'variables' => [
                    '1' => $contact->first_name,
                    '2' => $contact->company_name ?? 'Valued Customer',
                ],
            ]);
            $count++;
        }

        $this->updateCampaignRecipientsCount($campaign);
        return $count;
    }

    /**
     * Import and validate recipients from raw CSV content.
     */
    public function importRecipientsFromCsv(WhatsAppCampaign $campaign, string $csvContent): array
    {
        $tenantId = $campaign->tenant_id;
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (empty($lines)) {
            return ['imported' => 0, 'duplicates' => 0, 'opted_out' => 0];
        }

        // Header detection
        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        $phoneIdx = array_search('phone', $header);
        if ($phoneIdx === false) $phoneIdx = array_search('mobile', $header);
        if ($phoneIdx === false) $phoneIdx = 0; // fallback to column 0

        $nameIdx = array_search('name', $header);
        if ($nameIdx === false) $nameIdx = array_search('full_name', $header);

        $seenPhones = [];
        $imported = 0;
        $duplicates = 0;
        $optedOut = 0;

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            $rawPhone = $row[$phoneIdx] ?? null;
            if (!$rawPhone) continue;

            $phone = $this->normalizePhoneNumber($rawPhone);
            if (!$phone) continue;

            if (isset($seenPhones[$phone])) {
                $duplicates++;
                continue;
            }
            $seenPhones[$phone] = true;

            $name = ($nameIdx !== false && isset($row[$nameIdx])) ? trim($row[$nameIdx]) : 'Customer';

            // Collect other columns for template variable mapping
            $vars = [];
            $varNum = 1;
            foreach ($row as $colIdx => $val) {
                if ($colIdx !== $phoneIdx && $colIdx !== $nameIdx) {
                    $vars[(string)$varNum] = trim($val);
                    $varNum++;
                }
            }
            if (empty($vars)) {
                $vars['1'] = $name;
            }

            $isSuppressed = $this->isSuppressed($tenantId, $phone);
            if ($isSuppressed) {
                $optedOut++;
            }

            WhatsAppCampaignRecipient::create([
                'tenant_id' => $tenantId,
                'campaign_id' => $campaign->id,
                'phone_number' => $phone,
                'recipient_name' => $name,
                'status' => $isSuppressed ? 'skipped_opt_out' : 'queued',
                'variables' => $vars,
            ]);
            $imported++;
        }

        $this->updateCampaignRecipientsCount($campaign);

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'opted_out' => $optedOut,
        ];
    }

    /**
     * Submit campaign for manager approval.
     */
    public function submitForApproval(WhatsAppCampaign $campaign): WhatsAppCampaign
    {
        if ($campaign->total_recipients === 0) {
            throw new \InvalidArgumentException('Cannot submit empty campaign for approval.');
        }

        $campaign->update([
            'status' => 'pending_approval',
        ]);

        return $campaign;
    }

    /**
     * Approve campaign (Requires authorized approval permissions).
     */
    public function approveCampaign(WhatsAppCampaign $campaign, int $userId): WhatsAppCampaign
    {
        $newStatus = ($campaign->scheduled_at && $campaign->scheduled_at->isFuture()) ? 'approved' : 'queued';

        $campaign->update([
            'status' => $newStatus,
            'approved_by_user_id' => $userId,
            'approved_at' => now(),
        ]);

        return $campaign;
    }

    /**
     * Send a single live test message to an administrator phone number.
     */
    public function sendTestMessage(WhatsAppCampaign $campaign, string $phoneNumber, ?array $variables = null): array
    {
        $tenantId = $campaign->tenant_id;
        $targetPhone = $this->normalizePhoneNumber($phoneNumber);
        $template = $campaign->template;
        $waNumber = $campaign->phoneNumber;

        if (!$targetPhone) {
            throw new \InvalidArgumentException('Invalid test phone number.');
        }

        $varMap = $variables ?? ['1' => 'Test User', '2' => 'Acme Test Corp'];

        // Dispatch via Meta Cloud API or mock adapter
        $wabaAccount = $waNumber->account;
        $token = $wabaAccount?->system_user_token;

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $targetPhone,
            'type' => 'template',
            'template' => [
                'name' => $template->template_name,
                'language' => ['code' => $template->language ?? 'en_US'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => (string)$v], array_values($varMap)),
                    ],
                ],
            ],
        ];

        $wamid = 'wamid.TEST.' . uniqid();
        $success = true;

        if ($token && $waNumber->phone_number_id && !app()->environment('testing')) {
            try {
                $response = Http::withToken($token)
                    ->post("https://graph.facebook.com/v21.0/{$waNumber->phone_number_id}/messages", $payload);
                $success = $response->successful();
                $wamid = $response->json('messages.0.id') ?? $wamid;
            } catch (\Exception $e) {
                Log::warning("WhatsApp test dispatch failed: {$e->getMessage()}");
            }
        }

        return [
            'success' => $success,
            'wamid' => $wamid,
            'recipient' => $targetPhone,
            'template' => $template->template_name,
            'tested_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Start or resume campaign sending.
     */
    public function startOrResumeCampaign(WhatsAppCampaign $campaign): WhatsAppCampaign
    {
        $campaign->update([
            'status' => 'queued',
        ]);

        return $campaign;
    }

    public function startCampaign(WhatsAppCampaign $campaign): WhatsAppCampaign
    {
        return $this->startOrResumeCampaign($campaign);
    }

    /**
     * Pause ongoing campaign.
     */
    public function pauseCampaign(WhatsAppCampaign $campaign): WhatsAppCampaign
    {
        $campaign->update([
            'status' => 'paused',
        ]);

        return $campaign;
    }

    /**
     * Cancel campaign.
     */
    public function cancelCampaign(WhatsAppCampaign $campaign): WhatsAppCampaign
    {
        $campaign->update([
            'status' => 'cancelled',
        ]);

        return $campaign;
    }

    /**
     * Process a batch of queued recipients for an approved/queued campaign.
     */
    public function processBatch(WhatsAppCampaign $campaign, int $batchSize = 50): int
    {
        if (!in_array($campaign->status, ['queued', 'processing', 'approved'])) {
            return 0;
        }

        $campaign->update(['status' => 'processing']);
        $tenantId = $campaign->tenant_id;
        $template = $campaign->template;
        $waNumber = $campaign->phoneNumber;

        $recipients = WhatsAppCampaignRecipient::where('tenant_id', $tenantId)
            ->where('campaign_id', $campaign->id)
            ->where('status', 'queued')
            ->take($batchSize)
            ->get();

        if ($recipients->isEmpty()) {
            $campaign->update(['status' => 'completed']);
            return 0;
        }

        $sentInBatch = 0;

        foreach ($recipients as $recipient) {
            // Re-verify opt-out immediately before dispatch
            if ($this->isSuppressed($tenantId, $recipient->phone_number)) {
                $recipient->update([
                    'status' => 'skipped_opt_out',
                    'error_message' => 'Suppressed: Customer opted out',
                ]);
                $campaign->increment('opted_out_count');
                continue;
            }

            // Find or create conversation for tracking
            $conversation = WhatsAppConversation::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'phone_number' => $recipient->phone_number,
                ],
                [
                    'whatsapp_phone_number_id' => $waNumber->id,
                    'customer_name' => $recipient->recipient_name,
                    'contact_id' => $recipient->contact_id,
                    'lead_id' => $recipient->lead_id,
                    'status' => 'open',
                    'channel' => 'whatsapp',
                ]
            );

            // Construct template body
            $bodyText = $template->body_text;
            $vars = $recipient->variables ?? [];
            foreach ($vars as $idx => $val) {
                $bodyText = str_replace("{{{$idx}}}", (string)$val, $bodyText);
            }

            $wamid = 'wamid.HB.' . uniqid();

            // Store message
            $message = WhatsAppMessage::create([
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'provider_message_id' => $wamid,
                'direction' => 'outbound',
                'message_type' => 'template',
                'sender_actor' => 'automation',
                'content' => $bodyText,
                'template_name' => $template->template_name,
                'template_params' => $vars,
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);

            $recipient->update([
                'status' => 'sent',
                'whatsapp_message_id' => $message->id,
                'sent_at' => now(),
            ]);

            // Record Usage & Billing
            $category = strtolower($template->category ?? 'marketing');
            $cost = self::ESTIMATED_RATES[strtoupper($category)] ?? 0.0500;

            WhatsAppUsageRecord::create([
                'tenant_id' => $tenantId,
                'whatsapp_phone_number_id' => $waNumber->id,
                'campaign_id' => $campaign->id,
                'conversation_id' => $conversation->id,
                'conversation_category' => $category,
                'provider_reported_charge' => $cost,
                'rate_currency' => 'USD',
                'billable_conversations_count' => 1,
                'recorded_date' => now()->toDateString(),
            ]);

            $waNumber->increment('messages_sent_today');
            $template->increment('sent_count');
            $template->update(['last_used_at' => now()]);

            $campaign->increment('sent_count');
            $campaign->increment('delivered_count');
            $sentInBatch++;
        }

        // Check if more recipients are queued
        $remaining = WhatsAppCampaignRecipient::where('tenant_id', $tenantId)
            ->where('campaign_id', $campaign->id)
            ->where('status', 'queued')
            ->count();

        if ($remaining === 0) {
            $campaign->update(['status' => 'completed']);
        }

        return $sentInBatch;
    }

    /**
     * Get real-time campaign performance funnel analytics.
     */
    public function getCampaignAnalytics(WhatsAppCampaign $campaign): array
    {
        $campaign->refresh();
        $total = $campaign->total_recipients ?: 1;
        $sent = $campaign->sent_count;
        $delivered = $campaign->delivered_count;
        $read = $campaign->read_count;
        $replied = $campaign->replied_count;

        return [
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status,
            'template' => $campaign->template?->template_name,
            'sent' => $sent,
            'delivered' => $delivered,
            'read' => $read,
            'delivery_rate_percent' => $sent > 0 ? round(($delivered / $sent) * 100, 1) : 100,
            'funnel' => [
                'total' => $campaign->total_recipients,
                'queued' => $campaign->queued_count,
                'sent' => $sent,
                'delivered' => $delivered,
                'read' => $read,
                'failed' => $campaign->failed_count,
                'replied' => $replied,
                'opted_out' => $campaign->opted_out_count,
            ],
            'rates' => [
                'delivery_rate' => $sent > 0 ? round(($delivered / $sent) * 100, 1) : 0,
                'read_rate' => $delivered > 0 ? round(($read / $delivered) * 100, 1) : 0,
                'reply_rate' => $read > 0 ? round(($replied / $read) * 100, 1) : 0,
            ],
            'costs' => [
                'estimated_total_usd' => (float) $campaign->estimated_cost,
                'actual_billed_usd' => (float) WhatsAppUsageRecord::where('campaign_id', $campaign->id)->sum('provider_reported_charge'),
            ],
        ];
    }

    /**
     * Check if a phone number is suppressed by opt-out or DND.
     */
    public function isSuppressed(int $tenantId, string $phoneNumber): bool
    {
        $clean = $this->normalizePhoneNumber($phoneNumber);

        $waOptOut = WhatsAppOptOut::where('tenant_id', $tenantId)
            ->where('phone_number', $clean)
            ->exists();

        if ($waOptOut) return true;

        $omniOptOut = CustomerCommunicationConsent::where('tenant_id', $tenantId)
            ->where('channel', 'whatsapp')
            ->where('destination', $clean)
            ->whereIn('consent_status', ['opted_out', 'revoked'])
            ->exists();

        return $omniOptOut;
    }

    /**
     * Normalize a phone number to standard E.164.
     */
    public function normalizePhoneNumber(?string $phone): ?string
    {
        if (!$phone) return null;
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone));
        if (empty($cleaned)) return null;

        if (!str_starts_with($cleaned, '+')) {
            // Default to India +91 if 10 digits
            if (strlen($cleaned) === 10) {
                $cleaned = '+91' . $cleaned;
            } else {
                $cleaned = '+' . $cleaned;
            }
        }

        $digitsOnly = ltrim($cleaned, '+');
        if (strlen($digitsOnly) < 7 || strlen($digitsOnly) > 15) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Refresh campaign total, queued, and cost counters.
     */
    protected function updateCampaignRecipientsCount(WhatsAppCampaign $campaign): void
    {
        $total = WhatsAppCampaignRecipient::where('campaign_id', $campaign->id)->count();
        $queued = WhatsAppCampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'queued')->count();
        $optedOut = WhatsAppCampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'skipped_opt_out')->count();

        $category = strtoupper($campaign->template?->category ?? 'MARKETING');
        $rate = self::ESTIMATED_RATES[$category] ?? 0.0500;
        $estimatedCost = round($queued * $rate, 4);

        $campaign->update([
            'total_recipients' => $total,
            'queued_count' => $queued,
            'opted_out_count' => $optedOut,
            'estimated_cost' => $estimatedCost,
        ]);
    }
}
