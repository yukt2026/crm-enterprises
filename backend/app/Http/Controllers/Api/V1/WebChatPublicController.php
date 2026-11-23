<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Omnichannel\Adapters\WebChatChannelAdapter;
use App\Domains\Omnichannel\Models\WebsiteChatWidget;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebChatPublicController extends Controller
{
    public function __construct(
        protected WebChatChannelAdapter $webChatAdapter
    ) {}

    /**
     * Public widget bootstrap: validates widget key and origin.
     */
    public function init(Request $request): JsonResponse
    {
        $widgetKey = $request->header('X-Widget-Key') ?? $request->query('widget_key');

        if (!$widgetKey) {
            return response()->json(['success' => false, 'message' => 'Widget key required.'], 400);
        }

        $widget = WebsiteChatWidget::where('widget_key', $widgetKey)
            ->where('is_active', true)
            ->first();

        if (!$widget) {
            return response()->json(['success' => false, 'message' => 'Invalid or inactive chat widget.'], 404);
        }

        $visitorToken = 'vis_' . Str::random(24);

        return response()->json([
            'success' => true,
            'data' => [
                'widget_id' => $widget->id,
                'brand_title' => $widget->brand_title,
                'brand_color' => $widget->brand_color,
                'brand_logo_url' => $widget->brand_logo_url,
                'welcome_message' => $widget->welcome_message ?? 'Hi there! How can we help you today?',
                'pre_chat_fields' => $widget->pre_chat_fields ?? ['name', 'email', 'phone', 'requirement'],
                'visitor_token' => $visitorToken,
            ],
        ]);
    }

    /**
     * Send visitor message from website widget.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'widget_key' => 'required|string',
            'visitor_id' => 'required|string',
            'content' => 'required|string|max:2000',
            'name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'requirement' => 'nullable|string',
        ]);

        $widget = WebsiteChatWidget::where('widget_key', $validated['widget_key'])
            ->where('is_active', true)
            ->firstOrFail();

        $message = $this->webChatAdapter->handleInbound($widget->tenant_id, $validated);

        return response()->json([
            'success' => true,
            'data' => $message,
            'conversation_id' => $message->conversation_id,
        ]);
    }

    /**
     * Retrieve visitor chat history.
     */
    public function getMessages(Request $request): JsonResponse
    {
        $widgetKey = $request->query('widget_key');
        $visitorId = $request->query('visitor_id');

        if (!$widgetKey || !$visitorId) {
            return response()->json(['success' => false, 'message' => 'Parameters required.'], 400);
        }

        $widget = WebsiteChatWidget::where('widget_key', $widgetKey)->firstOrFail();

        $conversation = WhatsAppConversation::where('tenant_id', $widget->tenant_id)
            ->where('channel', 'webchat')
            ->where('channel_account_id', $visitorId)
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $messages = WhatsAppMessage::where('tenant_id', $widget->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->oldest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }
}
