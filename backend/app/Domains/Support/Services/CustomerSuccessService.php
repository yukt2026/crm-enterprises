<?php

namespace App\Domains\Support\Services;

use App\Domains\CRM\Models\Customer;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Support\Models\CustomerFeedback;
use App\Domains\Support\Models\CustomerHealthScore;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\WarrantyContract;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class CustomerSuccessService
{
    /**
     * Compute an explainable Customer Health Score (0 - 100) based on real CRM data:
     * - Ticket volume & resolution performance (30%)
     * - Customer Satisfaction (CSAT/NPS) rating history (25%)
     * - Payment & Invoicing overdue compliance (25%)
     * - Onboarding & Engagement status (20%)
     */
    public function calculateCustomerHealthScore(int $tenantId, int $customerId): CustomerHealthScore
    {
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($customerId);

        // 1. Support Ticket Health Factor (Weight: 30%)
        $tickets = SupportTicket::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->get();

        $openTicketsCount = $tickets->whereNotIn('status', ['resolved', 'closed'])->count();
        $breachedTicketsCount = $tickets->where('sla_status', 'breached')->count();
        $reopenedCount = $tickets->sum('reopen_count');

        $ticketDeduction = ($openTicketsCount * 10) + ($breachedTicketsCount * 25) + ($reopenedCount * 15);
        $ticketFactor = max(10.0, 100.0 - $ticketDeduction);
        $ticketExplanation = "{$openTicketsCount} open tickets, {$breachedTicketsCount} SLA breaches, {$reopenedCount} reopenings.";

        // 2. Customer Satisfaction Factor (Weight: 25%)
        $feedbacks = CustomerFeedback::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->get();

        if ($feedbacks->isNotEmpty() && $feedbacks->avg('csat_score') > 0) {
            $avgCsat = (float) $feedbacks->avg('csat_score'); // 1 to 5
            $csatFactor = round(($avgCsat / 5) * 100, 2);
            $csatExplanation = "Average CSAT: " . number_format($avgCsat, 1) . "/5 from {$feedbacks->count()} feedback responses.";
        } else {
            $csatFactor = 85.0; // Baseline neutral satisfaction
            $csatExplanation = "No negative customer feedback recorded; standard baseline applied.";
        }

        // 3. Payment & AR Overdue Factor (Weight: 25%)
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereNotIn('status', ['void', 'cancelled', 'draft'])
            ->get();

        $overdueAmount = (float) $invoices->where('due_date', '<', now()->toDateString())
            ->where('outstanding_amount', '>', 0)
            ->sum('outstanding_amount');

        if ($overdueAmount > 500000) {
            $paymentFactor = 20.0;
            $paymentExplanation = "Critical overdue AR balance: ₹" . number_format($overdueAmount, 2);
        } elseif ($overdueAmount > 50000) {
            $paymentFactor = 50.0;
            $paymentExplanation = "Moderate overdue invoices: ₹" . number_format($overdueAmount, 2);
        } elseif ($overdueAmount > 0) {
            $paymentFactor = 75.0;
            $paymentExplanation = "Minor outstanding overdue dues: ₹" . number_format($overdueAmount, 2);
        } else {
            $paymentFactor = 100.0;
            $paymentExplanation = "All invoices cleared or current without delinquency.";
        }

        // 4. Onboarding & Engagement Factor (Weight: 20%)
        $existingRecord = CustomerHealthScore::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->first();

        $onboardingStatus = $existingRecord?->onboarding_status ?? 'completed';
        $onboardingFactor = match ($onboardingStatus) {
            'completed' => 100.0,
            'in_progress' => 75.0,
            'stalled' => 35.0,
            default => 50.0,
        };
        $onboardingExplanation = "Client onboarding is '{$onboardingStatus}'.";

        // Composite Weighted Calculation
        $compositeScore = round(
            ($ticketFactor * 0.30) +
            ($csatFactor * 0.25) +
            ($paymentFactor * 0.25) +
            ($onboardingFactor * 0.20),
            2
        );

        // Derive Status & Churn Risk
        $healthStatus = match (true) {
            $compositeScore >= 75 => 'healthy',
            $compositeScore >= 50 => 'neutral',
            $compositeScore >= 30 => 'at_risk',
            default => 'critical',
        };

        $churnRisk = match ($healthStatus) {
            'healthy' => 'low',
            'neutral' => 'medium',
            default => 'high',
        };

        $factors = [
            'ticket_support' => [
                'score' => $ticketFactor,
                'weight_percent' => 30,
                'explanation' => $ticketExplanation,
            ],
            'customer_satisfaction' => [
                'score' => $csatFactor,
                'weight_percent' => 25,
                'explanation' => $csatExplanation,
            ],
            'financial_health' => [
                'score' => $paymentFactor,
                'weight_percent' => 25,
                'explanation' => $paymentExplanation,
            ],
            'onboarding_engagement' => [
                'score' => $onboardingFactor,
                'weight_percent' => 20,
                'explanation' => $onboardingExplanation,
            ],
            'summary_narrative' => "Health score is {$compositeScore}/100 ({$healthStatus}) with {$churnRisk} churn risk. " .
                "Driven by: {$ticketExplanation} {$paymentExplanation}",
        ];

        return CustomerHealthScore::updateOrCreate(
            ['tenant_id' => $tenantId, 'customer_id' => $customerId],
            [
                'health_score' => $compositeScore,
                'health_status' => $healthStatus,
                'churn_risk_level' => $churnRisk,
                'onboarding_status' => $onboardingStatus,
                'contract_value' => $existingRecord?->contract_value ?? $invoices->sum('total_amount'),
                'renewal_date' => $existingRecord?->renewal_date ?? now()->addMonths(6)->toDateString(),
                'factors' => $factors,
            ]
        );
    }

    /**
     * Get or refresh health scores across all customers.
     */
    public function getHealthScores(int $tenantId, ?string $status = null, ?string $churnRisk = null): Collection
    {
        $customers = Customer::where('tenant_id', $tenantId)->get();
        foreach ($customers as $c) {
            $this->calculateCustomerHealthScore($tenantId, $c->id);
        }

        $query = CustomerHealthScore::where('tenant_id', $tenantId)->with(['customer', 'successOwner']);
        if ($status) {
            $query->where('health_status', $status);
        }
        if ($churnRisk) {
            $query->where('churn_risk_level', $churnRisk);
        }

        return $query->orderBy('health_score', 'asc')->get();
    }

    /**
     * Log a Customer Success check-in meeting or review task.
     */
    public function recordCheckIn(int $tenantId, int $customerId, array $data, ?User $user = null): CustomerHealthScore
    {
        $record = CustomerHealthScore::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $record->last_check_in_date = now()->toDateString();
        $record->next_check_in_date = $data['next_check_in_date'] ?? now()->addDays(30)->toDateString();
        $record->check_in_notes = $data['notes'] ?? $record->check_in_notes;
        if ($user) {
            $record->success_owner_id = $user->id;
        }
        if (!empty($data['onboarding_status'])) {
            $record->onboarding_status = $data['onboarding_status'];
        }
        $record->save();

        // Re-evaluate score with new check-in data
        return $this->calculateCustomerHealthScore($tenantId, $customerId);
    }

    /**
     * Create a Warranty or Annual Maintenance Contract (AMC).
     */
    public function createWarrantyContract(int $tenantId, array $data, ?User $user = null): WarrantyContract
    {
        $contractNo = $data['contract_number'] ?? ('AMC-' . date('Y') . '-' . strtoupper(\Illuminate\Support\Str::random(6)));
        $startDate = $data['start_date'] ?? now()->toDateString();
        $endDate = $data['end_date'] ?? now()->addYear()->toDateString();

        return WarrantyContract::create([
            'tenant_id' => $tenantId,
            'customer_id' => $data['customer_id'],
            'contract_type' => $data['contract_type'] ?? 'amc',
            'contract_number' => $contractNo,
            'product_id' => $data['product_id'] ?? null,
            'product_name' => $data['product_name'] ?? 'Enterprise Cloud Infrastructure Support',
            'serial_number' => $data['serial_number'] ?? null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'coverage_details' => $data['coverage_details'] ?? '24x7 Priority SLA, quarterly preventive maintenance, on-site diagnostics.',
            'renewal_date' => $endDate,
            'renewal_price' => $data['renewal_price'] ?? 50000.00,
            'status' => 'active',
            'assigned_engineer_id' => $data['assigned_engineer_id'] ?? $user?->id,
            'preventive_maintenance_interval_days' => $data['preventive_maintenance_interval_days'] ?? 90,
            'next_service_date' => now()->addDays(90)->toDateString(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Record preventive maintenance execution on a Warranty/AMC contract.
     */
    public function recordMaintenance(int $tenantId, int $contractId, array $data, ?User $user = null): WarrantyContract
    {
        $contract = WarrantyContract::where('tenant_id', $tenantId)->findOrFail($contractId);

        $contract->last_service_date = now()->toDateString();
        $interval = $contract->preventive_maintenance_interval_days ?: 90;
        $contract->next_service_date = now()->addDays($interval)->toDateString();
        if (!empty($data['notes'])) {
            $contract->notes = ($contract->notes ? $contract->notes . "\n" : "") .
                "[" . date('Y-m-d') . " Maintenance]: " . $data['notes'];
        }
        $contract->save();

        return $contract;
    }
}
