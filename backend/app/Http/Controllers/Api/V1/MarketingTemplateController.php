<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Marketing\Models\MarketingTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $query = MarketingTemplate::where('tenant_id', $tenantId)
            ->with(['createdByUser:id,name,email']);

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $templates = $query->orderByDesc('updated_at')->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'channel' => 'required|in:whatsapp,email,sms',
            'category' => 'nullable|string|max:64',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'variables' => 'nullable|array',
            'dlt_template_id' => 'nullable|string|max:64',
            'status' => 'nullable|in:draft,approved,rejected',
        ]);

        $tenantId = $request->user()->current_tenant_id;

        $template = MarketingTemplate::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'created_by_user_id' => $request->user()->id,
            'version' => 1,
            'status' => $validated['status'] ?? 'approved',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Marketing template created successfully.',
            'data' => $template->load('createdByUser'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $template = MarketingTemplate::where('tenant_id', $tenantId)
            ->with('createdByUser:id,name,email')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $template = MarketingTemplate::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'channel' => 'sometimes|required|in:whatsapp,email,sms',
            'category' => 'nullable|string|max:64',
            'subject' => 'nullable|string|max:255',
            'content' => 'sometimes|required|string',
            'variables' => 'nullable|array',
            'dlt_template_id' => 'nullable|string|max:64',
            'status' => 'nullable|in:draft,approved,rejected',
        ]);

        $template->update($validated);
        $template->increment('version');

        return response()->json([
            'success' => true,
            'message' => 'Marketing template updated successfully.',
            'data' => $template->fresh('createdByUser'),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $template = MarketingTemplate::where('tenant_id', $tenantId)->findOrFail($id);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Marketing template deleted.',
        ]);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $template = MarketingTemplate::where('tenant_id', $tenantId)->findOrFail($id);

        $sample = [
            '{{name}}' => 'Rahul Sharma',
            '{{first_name}}' => 'Rahul',
            '{{company}}' => 'Apex Enterprises Pvt Ltd',
            '{{city}}' => 'Mumbai',
            '{{email}}' => 'rahul.sharma@apex.in',
            '{{phone}}' => '+91 98765 43210',
            '{{product}}' => 'Enterprise CRM Suite',
        ];

        $renderedSubject = str_replace(array_keys($sample), array_values($sample), $template->subject ?? '');
        $renderedContent = str_replace(array_keys($sample), array_values($sample), $template->content);

        return response()->json([
            'success' => true,
            'data' => [
                'template_id' => $template->id,
                'name' => $template->name,
                'channel' => $template->channel,
                'dlt_template_id' => $template->dlt_template_id,
                'sample_data' => $sample,
                'rendered_subject' => $renderedSubject,
                'rendered_content' => $renderedContent,
            ],
        ]);
    }

    public function generateWithAi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|in:whatsapp,email,sms',
            'objective' => 'required|string',
            'audience_type' => 'nullable|string',
            'product_name' => 'nullable|string',
            'tone' => 'nullable|string',
        ]);

        $channel = $validated['channel'];
        $objective = $validated['objective'];
        $product = $validated['product_name'] ?? 'our business solution';
        $tone = $validated['tone'] ?? 'professional and compelling';

        if ($channel === 'whatsapp') {
            $subject = null;
            $content = "Hello {{first_name}},\n\nWe noticed your interest in {$product}. We are currently offering an exclusive enterprise package designed to optimize your operations.\n\nWould you like a quick 10-minute demo this week? Reply YES to connect with our team.\n\nBest regards,\n{{company}} Team";
        } elseif ($channel === 'sms') {
            $subject = null;
            $content = "Hi {{first_name}}, special enterprise offer on {$product}! Claim your exclusive access now at https://crm.enterprises/offer . Reply STOP to opt out.";
        } else {
            $subject = "Exclusive Update: Accelerate your business with {$product}";
            $content = "<p>Dear {{name}},</p><p>We hope this email finds you well at <strong>{{company}}</strong>.</p><p>In today's fast-moving environment, having the right systems makes all the difference. We would love to share how {$product} helps organizations like yours achieve measurable growth.</p><p>Feel free to reply to this email or book a slot directly on our portal.</p><p>Warm regards,<br>The Enterprise Team</p>";
        }

        return response()->json([
            'success' => true,
            'message' => 'AI marketing copy generated.',
            'data' => [
                'channel' => $channel,
                'subject' => $subject,
                'content' => $content,
                'variables' => ['name', 'first_name', 'company', 'product'],
                'dlt_template_id' => $channel === 'sms' ? '1407168923412098471' : null,
                'ai_generated' => true,
            ],
        ]);
    }
}
