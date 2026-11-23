<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\CRM\Models\SalesQuota;
use App\Domains\CRM\Models\SalesTeam;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class SalesTeamService
{
    /**
     * Generate sales performance leaderboard with revenue, deals won, quota attainment, and activities.
     */
    public function getLeaderboard(int $tenantId, ?string $period = 'monthly', ?int $teamId = null): array
    {
        $now = Carbon::now();
        $startDate = match ($period) {
            'quarterly' => $now->copy()->firstOfQuarter()->toDateString(),
            'yearly' => $now->copy()->startOfYear()->toDateString(),
            default => $now->copy()->startOfMonth()->toDateString(),
        };
        $endDate = match ($period) {
            'quarterly' => $now->copy()->endOfQuarter()->toDateString(),
            'yearly' => $now->copy()->endOfYear()->toDateString(),
            default => $now->copy()->endOfMonth()->toDateString(),
        };

        // Query sales users in workspace
        $salesUsersQuery = User::whereHas('workspaces', function ($q) use ($tenantId) {
            $q->where('tenants.id', $tenantId);
        });

        if ($teamId) {
            $team = SalesTeam::where('tenant_id', $tenantId)->find($teamId);
            if ($team && !empty($team->member_ids)) {
                $salesUsersQuery->whereIn('id', $team->member_ids);
            }
        }

        $users = $salesUsersQuery->get();

        $leaderboard = [];

        foreach ($users as $user) {
            // 1. Deals Won in Period
            $wonDeals = Deal::where('tenant_id', $tenantId)
                ->where('assigned_user_id', $user->id)
                ->where('stage', 'won')
                ->whereBetween('won_at', [$startDate, $endDate])
                ->get();

            $revenueWon = (float) $wonDeals->sum('value');
            $dealsWonCount = $wonDeals->count();

            // 2. Deals Lost in Period
            $dealsLostCount = Deal::where('tenant_id', $tenantId)
                ->where('assigned_user_id', $user->id)
                ->where('stage', 'lost')
                ->whereBetween('lost_at', [$startDate, $endDate])
                ->count();

            $totalClosed = $dealsWonCount + $dealsLostCount;
            $winRate = $totalClosed > 0 ? round(($dealsWonCount / $totalClosed) * 100, 1) : 0;

            // 3. Quota Target for User
            $quota = SalesQuota::where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->where(function ($q) use ($startDate, $endDate, $period) {
                    $q->where('period', $period)
                      ->orWhere(function ($sub) use ($startDate, $endDate) {
                          $sub->whereDate('start_date', '<=', $endDate)
                              ->whereDate('end_date', '>=', $startDate);
                      });
                })
                ->latest('id')
                ->first();

            $targetRevenue = (float) ($quota?->target_revenue ?? 0);
            $attainmentPercent = $targetRevenue > 0 ? round(($revenueWon / $targetRevenue) * 100, 1) : 0;

            // 4. Leads Handled & Tasks Completed
            $leadsCount = Lead::where('tenant_id', $tenantId)
                ->where('assigned_user_id', $user->id)
                ->count();

            $tasksCompleted = Task::where('tenant_id', $tenantId)
                ->where('assigned_user_id', $user->id)
                ->where('status', 'done')
                ->count();

            $leaderboard[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'revenue_won' => $revenueWon,
                'deals_won_count' => $dealsWonCount,
                'deals_lost_count' => $dealsLostCount,
                'win_rate' => $winRate,
                'target_revenue' => $targetRevenue,
                'attainment_percent' => $attainmentPercent,
                'leads_handled' => $leadsCount,
                'tasks_completed' => $tasksCompleted,
            ];
        }

        // Sort descending by revenue won
        usort($leaderboard, fn($a, $b) => $b['revenue_won'] <=> $a['revenue_won']);

        // Assign rankings
        foreach ($leaderboard as $idx => &$entry) {
            $entry['rank'] = $idx + 1;
        }

        return [
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'leaderboard' => $leaderboard,
        ];
    }

    /**
     * Filter query for reporting hierarchy access restrictions.
     */
    public function applyHierarchyScope($query, User $user, int $tenantId, string $assignedUserColumn = 'assigned_user_id')
    {
        // Platform Admins and Business Owners/Admins have unrestricted view
        if ($user->is_platform_admin || in_array($user->role, ['Business Owner', 'Business Admin'])) {
            return $query;
        }

        // Managers supervise their sales teams
        if ($user->role === 'Manager') {
            $team = SalesTeam::where('tenant_id', $tenantId)->where('leader_id', $user->id)->first();
            if ($team && !empty($team->member_ids)) {
                $memberIds = array_unique(array_merge($team->member_ids, [$user->id]));
                return $query->whereIn($assignedUserColumn, $memberIds);
            }
            return $query->where($assignedUserColumn, $user->id);
        }

        // Sales Executive / Employee: own records only
        return $query->where($assignedUserColumn, $user->id);
    }
}
