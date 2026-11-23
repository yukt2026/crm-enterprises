<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Domains\LeadAcquisition\Models\LeadSource;
use App\Domains\LeadAcquisition\Services\LeadIngestionPipeline;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LeadSourceWebhookController extends Controller
{
    public function __construct(protected LeadIngestionPipeline $pipeline) {}

    /**
     * Meta Lead Ads & WhatsApp Webhook Verification Challenge (GET)
     */
    public function verifyMetaWebhook(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode === 'subscribe' && !empty($token)) {
            // Check if any source in any workspace is configured with this verify token
            $matched = LeadSource::whereIn('provider', ['meta_ads', 'whatsapp'])
                ->get()
                ->first(function ($source) use ($token) {
                    $verifyToken = $source->config['webhook_verify_token'] ?? $source->credentials['verify_token'] ?? null;
                    return $verifyToken === $token;
                });

            if ($matched || $token === config('services.meta.verify_token', 'crm_meta_verify_token')) {
                return response($challenge, 200, ['Content-Type' => 'text/plain']);
            }
        }

        return response('Forbidden', 403);
    }

    /**
     * Meta Lead Ads Inbound Webhook (POST)
     */
    public function handleMetaWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $payload['_raw_body'] = $request->getContent();

        // Resolve source by page_id or default meta_ads source
        $pageId = $payload['entry'][0]['id'] ?? null;
        $source = null;
        if ($pageId) {
            $source = LeadSource::where('provider', 'meta_ads')->where('account_id', $pageId)->first();
        }
        if (!$source) {
            $source = LeadSource::where('provider', 'meta_ads')->first();
        }

        if (!$source) {
            return response()->json([
                'status' => 'error',
                'message' => 'No connected Meta Ads source found for incoming event.',
            ], 404);
        }

        $result = $this->pipeline->ingest(
            $source,
            $payload,
            $request->headers->all(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Google Ads Lead Form Webhook (POST)
     */
    public function handleGoogleAdsWebhook(Request $request, int $sourceId): JsonResponse
    {
        $source = LeadSource::findOrFail($sourceId);
        $payload = $request->all();
        $payload['_raw_body'] = $request->getContent();

        $result = $this->pipeline->ingest(
            $source,
            $payload,
            $request->headers->all(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * WhatsApp Inbound Message Webhook (POST)
     */
    public function handleWhatsAppWebhook(Request $request, int $sourceId): JsonResponse
    {
        $source = LeadSource::findOrFail($sourceId);
        $payload = $request->all();
        $payload['_raw_body'] = $request->getContent();

        $result = $this->pipeline->ingest(
            $source,
            $payload,
            $request->headers->all(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Generic / Google Forms Webhook (POST)
     */
    public function handleGenericWebhook(Request $request, int $sourceId): JsonResponse
    {
        $source = LeadSource::findOrFail($sourceId);
        $payload = $request->all();
        $payload['_raw_body'] = $request->getContent();

        $result = $this->pipeline->ingest(
            $source,
            $payload,
            $request->headers->all(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
