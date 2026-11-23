<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\WhatsApp\Models\WhatsAppPhoneNumber;
use App\Domains\WhatsApp\Models\WhatsAppUsageRecord;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WhatsAppUsageController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * Usage & billing summary analytics
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->toDateString() : Carbon::now()->subDays(30)->toDateString();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->toDateString() : Carbon::now()->toDateString();

        $query = WhatsAppUsageRecord::where('tenant_id', $tenantId)
            ->whereDate('recorded_date', '>=', $startDate)
            ->whereDate('recorded_date', '<=', $endDate);

        $totalCost = (float) $query->sum('provider_reported_charge');
        $totalRecords = $query->count();

        // Breakdown by conversation category
        $byCategory = WhatsAppUsageRecord::where('tenant_id', $tenantId)
            ->whereDate('recorded_date', '>=', $startDate)
            ->whereDate('recorded_date', '<=', $endDate)
            ->selectRaw('conversation_category, count(*) as count, sum(provider_reported_charge) as cost')
            ->groupBy('conversation_category')
            ->get();

        // Breakdown by phone number
        $phoneNumbers = WhatsAppPhoneNumber::where('tenant_id', $tenantId)->get();
        $byPhoneNumber = [];
        foreach ($phoneNumbers as $phone) {
            $phoneCost = (float) WhatsAppUsageRecord::where('whatsapp_phone_number_id', $phone->id)
                ->whereDate('recorded_date', '>=', $startDate)
                ->whereDate('recorded_date', '<=', $endDate)
                ->sum('provider_reported_charge');
            $phoneCount = WhatsAppUsageRecord::where('whatsapp_phone_number_id', $phone->id)
                ->whereDate('recorded_date', '>=', $startDate)
                ->whereDate('recorded_date', '<=', $endDate)
                ->count();

            $byPhoneNumber[] = [
                'phone_number_id' => $phone->id,
                'display_phone_number' => $phone->display_phone_number,
                'verified_name' => $phone->verified_name,
                'tier' => $phone->messaging_limit_tier,
                'messages_sent_today' => $phone->messages_sent_today,
                'conversations_count' => $phoneCount,
                'cost' => $phoneCost,
            ];
        }

        // Daily trend
        $dailyTrend = WhatsAppUsageRecord::where('tenant_id', $tenantId)
            ->whereDate('recorded_date', '>=', $startDate)
            ->whereDate('recorded_date', '<=', $endDate)
            ->selectRaw('recorded_date as date, count(*) as count, sum(provider_reported_charge) as cost')
            ->groupBy('recorded_date')
            ->orderBy('recorded_date', 'asc')
            ->get();

        return $this->successResponse([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'total_cost' => $totalCost,
            'total_conversations' => $totalRecords,
            'by_category' => $byCategory,
            'by_phone_number' => $byPhoneNumber,
            'daily_trend' => $dailyTrend,
            'currency' => 'USD',
        ], 'WhatsApp usage & billing summary retrieved.');
    }

    /**
     * Paginated usage records
     */
    public function records(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $query = WhatsAppUsageRecord::where('tenant_id', $tenantId)
            ->with(['phoneNumber', 'campaign'])
            ->orderBy('recorded_date', 'desc');

        if ($request->has('phone_number_id')) {
            $query->where('whatsapp_phone_number_id', $request->phone_number_id);
        }

        if ($request->has('category')) {
            $query->where('conversation_category', $request->category);
        }

        $records = $query->paginate($request->integer('per_page', 25));

        return $this->successResponse($records, 'WhatsApp usage records retrieved.');
    }
}
