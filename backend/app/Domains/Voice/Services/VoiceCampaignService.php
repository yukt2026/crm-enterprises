<?php

namespace App\Domains\Voice\Services;

use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Voice\Models\TelephonyOptOut;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use App\Domains\Voice\Models\VoiceCallingCampaign;
use App\Domains\Voice\Providers\TelephonyProviderManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class VoiceCampaignService
{
    protected TelephonyProviderManager $providerManager;
    protected VoiceCrmAutomationService $crmAutomation;

    public function __construct(
        TelephonyProviderManager $providerManager,
        VoiceCrmAutomationService $crmAutomation
    ) {
        $this->providerManager = $providerManager;
        $this->crmAutomation = $crmAutomation;
    }

    /**
     * Launch or execute a batch of an outbound calling campaign.
     */
    public function executeCampaignBatch(VoiceCallingCampaign $campaign, int $batchLimit = 5): array
    {
        $tenantId = $campaign->tenant_id;
        $agent = $campaign->agent;

        if (!$agent || $agent->status !== 'active') {
            throw new \DomainException("Campaign cannot run: Assigned Voice Agent is inactive or missing.");
        }

        // 1. Resolve Target Leads matching campaign filters
        $filters = $campaign->target_filters ?: [];
        $query = Lead::where('tenant_id', $tenantId)->whereNotNull('phone');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        // Exclude leads already successfully called or on DND list
        $optedOutNumbers = TelephonyOptOut::where('tenant_id', $tenantId)->pluck('phone_number')->toArray();
        $query->whereNotIn('phone', $optedOutNumbers);

        $leads = $query->limit($batchLimit)->get();
        $dispatched = 0;
        $skippedDnd = 0;

        $callerId = $agent->phoneNumber?->phone_number ?: '+919876543210';

        foreach ($leads as $lead) {
            // Check DND
            if (in_array($lead->phone, $optedOutNumbers)) {
                $skippedDnd++;
                continue;
            }

            try {
                $call = VoiceCall::create([
                    'tenant_id' => $tenantId,
                    'voice_agent_id' => $agent->id,
                    'campaign_id' => $campaign->id,
                    'lead_id' => $lead->id,
                    'direction' => 'outbound',
                    'from_number' => $callerId,
                    'to_number' => $lead->phone,
                    'provider' => $agent->phoneNumber?->provider?->provider ?: 'exotel',
                    'status' => 'queued',
                ]);

                $this->providerManager->dispatchCall($call, $agent);
                $dispatched++;
            } catch (\Exception $e) {
                Log::warning("Campaign #{$campaign->id} failed to dial lead #{$lead->id}: " . $e->getMessage());
            }
        }

        // Update campaign statistics
        $campaign->status = 'running';
        $campaign->total_contacts_called += $dispatched;
        $campaign->save();

        return [
            'campaign_id' => $campaign->id,
            'batch_size' => $leads->count(),
            'dispatched_calls' => $dispatched,
            'skipped_dnd' => $skippedDnd,
        ];
    }
}
