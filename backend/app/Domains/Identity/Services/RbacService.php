<?php

namespace App\Domains\Identity\Services;

use App\Domains\CRM\Models\SalesTeam;
use App\Domains\Identity\Models\CrmRole;
use App\Domains\Identity\Models\CrmRolePermission;
use App\Domains\Identity\Models\CrmUserRole;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RbacService
{
    /**
     * Complete list of all supported CRM modules.
     */
    public const MODULES = [
        'leads' => 'Leads & Enquiries',
        'contacts' => 'Contacts Roster',
        'companies' => 'Companies & Accounts',
        'deals' => 'Deals & Opportunities',
        'sales_teams' => 'Sales Teams & Quotas',
        'quotations' => 'Sales Quotations & Proposals',
        'invoices' => 'Invoices & Billing',
        'payments' => 'Payments & Transactions',
        'products' => 'Products & Services Catalog',
        'inventory' => 'Warehouses & Stock Ledger',
        'procurement' => 'Suppliers & Purchase Orders',
        'whatsapp' => 'WhatsApp Team Inbox & CRM',
        'voice' => 'Voice Telephony & AI Calling',
        'omnichannel' => 'Omnichannel Unified Inbox',
        'marketing' => 'Marketing Campaigns & Drips',
        'workflows' => 'Workflow Automation Builder',
        'landing_pages' => 'Landing Pages & Form Builder',
        'hrm' => 'HRM & Employee Directory',
        'attendance' => 'Attendance & Shifts',
        'leaves' => 'Leave Management',
        'projects' => 'Projects & Milestones',
        'tasks' => 'Tasks Manager',
        'meetings' => 'Meetings Calendar',
        'tickets' => 'Support Tickets & Helpdesk',
        'knowledge_base' => 'Knowledge Base & FAQs',
        'analytics' => 'Analytics & BI Reports',
        'integrations' => 'Integrations & API Keys',
        'billing' => 'Subscription & Credit Ledger',
        'branches' => 'Branches & Locations',
        'customer_portal' => 'Customer Portal & Appointments',
        'gst' => 'GST & Business Compliance',
        'field_sales' => 'Field Sales & Visit Planner',
        'settings' => 'System & Workspace Settings',
        'roles' => 'Roles & Permissions',
    ];

    /**
     * Standard granular action permissions.
     */
    public const ACTIONS = [
        'view' => 'View / Read Records',
        'create' => 'Create New Records',
        'edit' => 'Edit / Update Records',
        'delete' => 'Soft Delete Records',
        'restore' => 'Restore Deleted Records',
        'permanent_delete' => 'Permanently Purge Records',
        'import' => 'Bulk Import CSV/Excel',
        'export' => 'Export Data to Excel/CSV',
        'download' => 'Download Files & Attachments',
        'print' => 'Print / Generate PDF',
        'assign' => 'Assign Records to Users',
        'reassign' => 'Reassign Ownership',
        'approve' => 'Approve Requests / Workflows',
        'reject' => 'Reject Requests',
        'archive' => 'Archive Records',
        'share' => 'Share Records Externally',
        'manage_settings' => 'Configure Module Settings',
        'view_reports' => 'View Domain Analytics & Reports',
        'manage_integrations' => 'Configure API Integrations',
        'manage_templates' => 'Manage Reusable Templates',
        'execute_actions' => 'Execute Triggers & Automations',
        'initiate_calls' => 'Initiate Outbound Calls',
        'receive_calls' => 'Receive Inbound Calls',
        'view_logs' => 'View Call Logs',
        'view_own_recordings' => 'View Own Call Recordings',
        'view_all_recordings' => 'View All Organization Call Recordings',
        'download_recordings' => 'Download Call Recordings',
        'use_ai_calling' => 'Use AI Voice Calling',
        'manage_ai_agents' => 'Create & Manage AI Voice Agents',
        'manage_phone_numbers' => 'Manage Telephony Phone Numbers',
        'manage_ivr' => 'Manage IVR Flows & Queues',
        'manage_branches' => 'Manage Branches & Outlets',
        'view_own' => 'View Own Assigned Branch',
        'view_all' => 'View All Authorized Branches',
        'deactivate' => 'Deactivate Branch Records',
        'assign_employees' => 'Assign Staff to Branch',
        'transfer_records' => 'Transfer Records Across Branches',
        'view_consolidated_reports' => 'View Consolidated Multi-Branch Reports',
        'adjust_stock' => 'Adjust Warehouse Stock Levels',
        'transfer_stock' => 'Transfer Stock Between Warehouses',
        'approve_transfers' => 'Approve Stock Transfers',
        'view_vendor_bank_details' => 'View Sensitive Vendor Bank Details',
        'view_purchase_costs' => 'View Purchase & Product Cost Prices',
        'manage_returns' => 'Manage Customer Returns & Restocking',
        'test_workflows' => 'Test & Simulate Automation Workflows',
        'publish_workflows' => 'Publish & Activate Workflows',
        'pause_workflows' => 'Pause Active Workflows',
        'approve_automation' => 'Approve Human Workflow Actions',
        'view_execution_logs' => 'View Workflow Execution Logs',
        'manage_automation_limits' => 'Manage Organization Automation Limits',
        'view_dashboard' => 'View Analytics Dashboard',
        'configure_dashboard' => 'Configure Dashboard & Widgets',
        'view_sales_reports' => 'View Sales Analytics & Reports',
        'view_finance_reports' => 'View Finance Analytics & Reports',
        'view_employee_reports' => 'View Employee Analytics & Reports',
        'view_branch_reports' => 'View Branch Analytics & Reports',
        'export_reports' => 'Export Analytics & Custom Reports',
        'generate_ai_insights' => 'Generate AI Business Insights',
        'manage_alerts' => 'Configure Custom Analytics Alerts',
        'access_field_sales' => 'Access Field Sales Tracker',
        'access_location_info' => 'Access GPS Location Information',
        'manage_report_schedules' => 'Manage Scheduled Report Deliveries',
    ];

    /**
     * Supported row-level data scopes.
     */
    public const DATA_SCOPES = [
        'all' => 'All Organization Records',
        'branch' => 'Branch Records Only',
        'warehouse' => 'Warehouse Records Only',
        'department' => 'Department Records Only',
        'team' => 'Team Records Only',
        'assigned' => 'Assigned Records Only',
        'own' => 'Own Created Records Only',
        'selected_employees' => 'Selected Employees Records',
        'selected_resources' => 'Selected Resources Records',
    ];

    /**
     * Check if a user has permission to perform an action on a module and optionally a specific record.
     */
    public function can(User $user, string $module, string $action = 'view', $record = null): bool
    {
        // Platform Super Admin bypasses for platform console or root actions
        if ($user->is_platform_admin && $module === 'platform_console') {
            return true;
        }

        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        // Organization Owner has full access across all modules
        if ($this->isOrganizationOwner($user, $tenantId)) {
            return true;
        }

        // Retrieve effective permissions for user
        $effective = $this->getEffectivePermissions($user, $tenantId);

        // Check if module permission exists for action
        $modulePerms = $effective['modules'][$module] ?? null;
        if (!$modulePerms) {
            return false;
        }

        $actionPerm = $modulePerms['actions'][$action] ?? null;
        if (!$actionPerm && !isset($modulePerms['actions']['*'])) {
            return false;
        }

        $scope = $actionPerm['data_scope'] ?? $modulePerms['actions']['*']['data_scope'] ?? 'all';

        // If no specific record is provided, action is permitted
        if ($record === null) {
            return true;
        }

        // Validate row-level data scope against the record
        return $this->evaluateRecordScope($user, $scope, $record, $module);
    }

    /**
     * Check if a user has a specific permission in dot-notation e.g. "whatsapp.campaigns_approve".
     */
    public function hasPermission(User $user, string $permission): bool
    {
        if (str_contains($permission, '.')) {
            [$module, $action] = explode('.', $permission, 2);
            return $this->can($user, $module, $action);
        }

        return $this->can($user, $permission, 'view');
    }

    /**
     * Check if user is organization owner.
     */
    public function isOrganizationOwner(User $user, ?int $tenantId): bool
    {
        $roleName = strtolower($user->role ?? '');
        if (in_array($roleName, ['business owner', 'organization owner', 'business admin', 'admin', 'owner'])) {
            return true;
        }

        if ($tenantId) {
            $isOwner = TenantUser::where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->where(function ($q) {
                    $q->where('is_owner', true)
                        ->orWhere('role', 'Owner')
                        ->orWhere('role', 'Business Owner');
                })
                ->exists();

            if ($isOwner) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate row-level data scope against a specific model instance.
     */
    public function evaluateRecordScope(User $user, string $scope, $record, string $module): bool
    {
        if ($scope === 'all') {
            return true;
        }

        $assignedUserId = $record->assigned_user_id 
            ?? $record->assigned_to_user_id 
            ?? $record->owner_id 
            ?? $record->leader_id
            ?? $record->user_id 
            ?? null;

        $createdByUserId = $record->created_by_user_id 
            ?? $record->created_by 
            ?? $record->user_id 
            ?? null;

        if ($scope === 'own') {
            return (int)$createdByUserId === (int)$user->id;
        }

        if ($scope === 'assigned') {
            return (int)$assignedUserId === (int)$user->id || (int)$createdByUserId === (int)$user->id;
        }

        if ($scope === 'team') {
            $teamUserIds = $this->getUserTeamMemberIds($user);
            return in_array((int)$assignedUserId, $teamUserIds) || in_array((int)$createdByUserId, $teamUserIds);
        }

        if ($scope === 'department') {
            if (isset($record->department_id) && $user->department_id) {
                return (int)$record->department_id === (int)$user->department_id;
            }
            if (isset($record->department) && $user->department) {
                return strtolower($record->department) === strtolower($user->department);
            }
            return (int)$assignedUserId === (int)$user->id;
        }

        if ($scope === 'branch') {
            if (isset($record->branch_id) && $user->branch_id) {
                return (int)$record->branch_id === (int)$user->branch_id;
            }
            return (int)$assignedUserId === (int)$user->id;
        }

        return false;
    }

    /**
     * Apply row-level data scope automatically to an Eloquent Query Builder.
     */
    public function applyDataScope(Builder $query, User $user, string $module, string $action = 'view'): Builder
    {
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        // Organization Owner sees everything in tenant
        if ($this->isOrganizationOwner($user, $tenantId)) {
            return $query;
        }

        $effective = $this->getEffectivePermissions($user, $tenantId);
        $modulePerms = $effective['modules'][$module] ?? null;

        if (!$modulePerms) {
            return $query->whereRaw('1 = 0'); // Deny all
        }

        $actionPerm = $modulePerms['actions'][$action] ?? $modulePerms['actions']['*'] ?? null;
        if (!$actionPerm) {
            return $query->whereRaw('1 = 0');
        }

        $scope = $actionPerm['data_scope'] ?? 'all';

        switch ($scope) {
            case 'all':
                return $query;

            case 'own':
                return $query->where(function (Builder $sub) use ($user) {
                    $table = $sub->getModel()->getTable();
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'created_by_user_id')) {
                        $sub->where($table . '.created_by_user_id', $user->id);
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn($table, 'created_by')) {
                        $sub->where($table . '.created_by', $user->id);
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn($table, 'user_id')) {
                        $sub->where($table . '.user_id', $user->id);
                    } else {
                        $sub->whereRaw('1 = 0');
                    }
                });

            case 'assigned':
                return $query->where(function (Builder $sub) use ($user) {
                    $table = $sub->getModel()->getTable();
                    $added = false;

                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'assigned_user_id')) {
                        $sub->where($table . '.assigned_user_id', $user->id);
                        $added = true;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'assigned_to_user_id')) {
                        $added ? $sub->orWhere($table . '.assigned_to_user_id', $user->id) : $sub->where($table . '.assigned_to_user_id', $user->id);
                        $added = true;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'owner_id')) {
                        $added ? $sub->orWhere($table . '.owner_id', $user->id) : $sub->where($table . '.owner_id', $user->id);
                        $added = true;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'user_id')) {
                        $added ? $sub->orWhere($table . '.user_id', $user->id) : $sub->where($table . '.user_id', $user->id);
                        $added = true;
                    }

                    if (!$added) {
                        $sub->whereRaw('1 = 0');
                    }
                });

            case 'team':
                $teamIds = $this->getUserTeamMemberIds($user);
                return $query->where(function (Builder $sub) use ($teamIds) {
                    $table = $sub->getModel()->getTable();
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'assigned_user_id')) {
                        $sub->whereIn($table . '.assigned_user_id', $teamIds);
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn($table, 'user_id')) {
                        $sub->whereIn($table . '.user_id', $teamIds);
                    } else {
                        $sub->whereRaw('1 = 0');
                    }
                });

            case 'department':
                return $query->where(function (Builder $sub) use ($user) {
                    $table = $sub->getModel()->getTable();
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'department_id') && $user->department_id) {
                        $sub->where($table . '.department_id', $user->department_id);
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn($table, 'department') && $user->department) {
                        $sub->where($table . '.department', $user->department);
                    } else {
                        $sub->whereRaw('1 = 0');
                    }
                });

            case 'branch':
                return $query->where(function (Builder $sub) use ($user) {
                    $table = $sub->getModel()->getTable();
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'branch_id') && $user->branch_id) {
                        $sub->where($table . '.branch_id', $user->branch_id);
                    } else {
                        $sub->whereRaw('1 = 0');
                    }
                });

            default:
                return $query->whereRaw('1 = 0');
        }
    }

    /**
     * Get teammate user IDs for user.
     */
    public function getUserTeamMemberIds(User $user): array
    {
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;
        $teams = SalesTeam::where('tenant_id', $tenantId)->get();

        $memberIds = [$user->id];
        foreach ($teams as $team) {
            $isMember = in_array($user->id, $team->member_ids ?? []) || (int)$team->leader_id === (int)$user->id;
            if ($isMember) {
                if (is_array($team->member_ids)) {
                    $memberIds = array_merge($memberIds, $team->member_ids);
                }
                if ($team->leader_id) {
                    $memberIds[] = $team->leader_id;
                }
            }
        }

        return array_values(array_unique(array_map('intval', $memberIds)));
    }

    /**
     * Retrieve cached effective permissions for a user.
     */
    public function getEffectivePermissions(User $user, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? $user->tenant_id ?? $user->current_tenant_id;
        $cacheKey = "rbac_effective_perm_user_{$user->id}_tenant_{$tenantId}";

        return Cache::remember($cacheKey, 3600, function () use ($user, $tenantId) {
            // 1. Fetch assigned CrmRoles for this tenant
            $roles = CrmRole::whereHas('users', function ($q) use ($user, $tenantId) {
                $q->where('users.id', $user->id)
                  ->where('crm_user_roles.tenant_id', $tenantId);
            })->with('permissions')->where('is_active', true)->get();

            // 2. If no explicit CrmRole assigned, fall back to default preset by $user->role
            if ($roles->isEmpty()) {
                $roleSlug = str_replace('-', '_', Str::slug($user->role ?: 'employee', '_'));
                $presetRole = $this->getOrCreateRoleForSlug($roleSlug, $user->role ?: 'Employee', $tenantId);
                if ($presetRole) {
                    $roles = collect([$presetRole->load('permissions')]);
                }
            }

            // 3. Aggregate permissions across all roles (union permissions, most permissive data scope)
            $modules = [];
            $roleSummaries = [];

            foreach ($roles as $role) {
                $roleSummaries[] = [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'is_system' => $role->is_system,
                    'color' => $role->color,
                ];

                foreach ($role->permissions as $perm) {
                    $mod = $perm->module;
                    $act = $perm->action;
                    $scope = $perm->data_scope;

                    if (!isset($modules[$mod])) {
                        $modules[$mod] = [
                            'actions' => [],
                        ];
                    }

                    // Scope priority: all > branch > department > team > assigned > own
                    $existingScope = $modules[$mod]['actions'][$act]['data_scope'] ?? null;
                    $mergedScope = $this->mergeScopes($existingScope, $scope);

                    $modules[$mod]['actions'][$act] = [
                        'allowed' => true,
                        'data_scope' => $mergedScope,
                    ];
                }
            }

            return [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'roles' => $roleSummaries,
                'modules' => $modules,
            ];
        });
    }

    /**
     * Merge two scopes selecting the more permissive one.
     */
    protected function mergeScopes(?string $scopeA, string $scopeB): string
    {
        if (!$scopeA) return $scopeB;
        $order = ['all' => 6, 'branch' => 5, 'department' => 4, 'team' => 3, 'assigned' => 2, 'own' => 1];
        $valA = $order[$scopeA] ?? 0;
        $valB = $order[$scopeB] ?? 0;

        return $valA >= $valB ? $scopeA : $scopeB;
    }

    /**
     * Invalidate cached permissions for a user.
     */
    public function invalidateUserCache(int $userId): void
    {
        // Clear all tenant keys for this user
        $tenants = Tenant::pluck('id');
        foreach ($tenants as $tid) {
            Cache::forget("rbac_effective_perm_user_{$userId}_tenant_{$tid}");
        }
        Cache::forget("rbac_effective_perm_user_{$userId}_tenant_");
    }

    /**
     * Invalidate all users assigned to a role.
     */
    public function invalidateRoleUsersCache(CrmRole $role): void
    {
        $userIds = $role->users()->pluck('users.id');
        foreach ($userIds as $uid) {
            $this->invalidateUserCache($uid);
        }
    }

    /**
     * Create a new custom role with permissions.
     */
    public function createCustomRole(Tenant $tenant, array $data, array $permissions = []): CrmRole
    {
        return DB::transaction(function () use ($tenant, $data, $permissions) {
            $slug = Str::slug($data['name']);
            // Ensure unique slug within tenant
            $count = CrmRole::where('tenant_id', $tenant->id)->where('slug', 'like', "{$slug}%")->count();
            if ($count > 0) {
                $slug = "{$slug}-" . ($count + 1);
            }

            $role = CrmRole::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'is_system' => false,
                'is_active' => $data['is_active'] ?? true,
                'color' => $data['color'] ?? '#4F46E5',
                'parent_role_id' => $data['parent_role_id'] ?? null,
                'created_by_user_id' => $data['created_by_user_id'] ?? null,
            ]);

            $this->syncRolePermissions($role, $permissions);

            return $role->fresh(['permissions']);
        });
    }

    /**
     * Update an existing role and its permissions.
     */
    public function updateRole(CrmRole $role, array $data, ?array $permissions = null): CrmRole
    {
        return DB::transaction(function () use ($role, $data, $permissions) {
            $fillable = array_intersect_key($data, array_flip(['name', 'description', 'is_active', 'color']));
            
            // Protect system role slug and status if system preset
            if (!$role->is_system && isset($data['name']) && $data['name'] !== $role->name) {
                $fillable['slug'] = Str::slug($data['name']);
            }

            $role->update($fillable);

            if ($permissions !== null) {
                $this->syncRolePermissions($role, $permissions);
            }

            $this->invalidateRoleUsersCache($role);

            return $role->fresh(['permissions']);
        });
    }

    /**
     * Duplicate an existing role.
     */
    public function duplicateRole(CrmRole $sourceRole, string $newName, ?string $description = null, ?int $tenantId = null): CrmRole
    {
        return DB::transaction(function () use ($sourceRole, $newName, $description, $tenantId) {
            $targetTenantId = $tenantId ?? $sourceRole->tenant_id;
            $slug = Str::slug($newName);

            $newRole = CrmRole::create([
                'tenant_id' => $targetTenantId,
                'name' => $newName,
                'slug' => $slug,
                'description' => $description ?? "Cloned from {$sourceRole->name}",
                'is_system' => false,
                'is_active' => true,
                'color' => $sourceRole->color,
                'parent_role_id' => $sourceRole->id,
            ]);

            // Copy permissions
            foreach ($sourceRole->permissions as $perm) {
                CrmRolePermission::create([
                    'role_id' => $newRole->id,
                    'module' => $perm->module,
                    'action' => $perm->action,
                    'data_scope' => $perm->data_scope,
                    'scope_constraints' => $perm->scope_constraints,
                ]);
            }

            return $newRole->fresh(['permissions']);
        });
    }

    /**
     * Delete a role safely.
     */
    public function deleteRole(CrmRole $role): bool
    {
        if ($role->is_system) {
            throw new \InvalidArgumentException("Protected system roles cannot be deleted.");
        }

        $assignedCount = $role->users()->count();
        if ($assignedCount > 0) {
            throw new \InvalidArgumentException("Cannot delete role: {$assignedCount} active users are currently assigned to this role. Please reassign them first.");
        }

        $this->invalidateRoleUsersCache($role);
        return $role->delete();
    }

    /**
     * Synchronize permissions for a role.
     */
    public function syncRolePermissions(CrmRole $role, array $permissions): void
    {
        CrmRolePermission::where('role_id', $role->id)->delete();

        foreach ($permissions as $perm) {
            if (empty($perm['module']) || empty($perm['action'])) {
                continue;
            }

            CrmRolePermission::create([
                'role_id' => $role->id,
                'module' => $perm['module'],
                'action' => $perm['action'],
                'data_scope' => $perm['data_scope'] ?? 'all',
                'scope_constraints' => $perm['scope_constraints'] ?? null,
            ]);
        }

        $this->invalidateRoleUsersCache($role);
    }

    /**
     * Copy permissions from one role to another.
     */
    public function copyPermissions(CrmRole $sourceRole, CrmRole $targetRole): void
    {
        DB::transaction(function () use ($sourceRole, $targetRole) {
            CrmRolePermission::where('role_id', $targetRole->id)->delete();

            foreach ($sourceRole->permissions as $perm) {
                CrmRolePermission::create([
                    'role_id' => $targetRole->id,
                    'module' => $perm->module,
                    'action' => $perm->action,
                    'data_scope' => $perm->data_scope,
                    'scope_constraints' => $perm->scope_constraints,
                ]);
            }

            $this->invalidateRoleUsersCache($targetRole);
        });
    }

    /**
     * Compare permissions between two roles.
     */
    public function compareRoles(CrmRole $roleA, CrmRole $roleB): array
    {
        $permsA = $roleA->permissions->keyBy(fn($p) => "{$p->module}:{$p->action}");
        $permsB = $roleB->permissions->keyBy(fn($p) => "{$p->module}:{$p->action}");

        $allKeys = $permsA->keys()->merge($permsB->keys())->unique()->values();

        $comparison = [];
        foreach ($allKeys as $key) {
            [$module, $action] = explode(':', $key);
            $pA = $permsA->get($key);
            $pB = $permsB->get($key);

            $comparison[] = [
                'module' => $module,
                'action' => $action,
                'role_a_has' => $pA !== null,
                'role_a_scope' => $pA ? $pA->data_scope : null,
                'role_b_has' => $pB !== null,
                'role_b_scope' => $pB ? $pB->data_scope : null,
                'is_different' => ($pA === null && $pB !== null) 
                    || ($pA !== null && $pB === null) 
                    || ($pA && $pB && $pA->data_scope !== $pB->data_scope),
            ];
        }

        return $comparison;
    }

    /**
     * Assign role to user.
     */
    public function assignUserRole(User $user, CrmRole $role, bool $isPrimary = false, ?int $assignedBy = null): void
    {
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        if ($isPrimary) {
            CrmUserRole::where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->update(['is_primary' => false]);
        }

        CrmUserRole::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'role_id' => $role->id,
            ],
            [
                'is_primary' => $isPrimary,
                'assigned_by_user_id' => $assignedBy,
            ]
        );

        $this->invalidateUserCache($user->id);
    }

    /**
     * Remove role from user.
     */
    public function removeUserRole(User $user, CrmRole $role): void
    {
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        CrmUserRole::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->delete();

        $this->invalidateUserCache($user->id);
    }

    /**
     * Seed or retrieve system default role presets.
     */
    public function getOrCreateRoleForSlug(string $slug, string $name, ?int $tenantId = null): ?CrmRole
    {
        $normalizedSlug = str_replace('-', '_', Str::slug($slug, '_'));

        $role = CrmRole::where(function ($q) use ($slug, $normalizedSlug) {
                $q->where('slug', $slug)->orWhere('slug', $normalizedSlug);
            })
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id');
                if ($tenantId) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            })->first();

        if ($role) {
            return $role;
        }

        // Initialize default preset if missing
        $defaults = $this->getDefaultRoleDefinitions();
        $def = $defaults[$normalizedSlug] ?? $defaults[$slug] ?? null;

        if (!$def) {
            return null;
        }

        return DB::transaction(function () use ($normalizedSlug, $def, $tenantId) {
            $role = CrmRole::create([
                'tenant_id' => null, // Global system preset
                'name' => $def['name'],
                'slug' => $normalizedSlug,
                'description' => $def['description'],
                'is_system' => true,
                'is_active' => true,
                'color' => $def['color'] ?? '#4F46E5',
            ]);

            foreach ($def['permissions'] as $perm) {
                CrmRolePermission::create([
                    'role_id' => $role->id,
                    'module' => $perm['module'],
                    'action' => $perm['action'],
                    'data_scope' => $perm['data_scope'] ?? 'all',
                ]);
            }

            return $role->fresh(['permissions']);
        });
    }

    /**
     * Seed all 18 default system role presets with fine-tuned permissions.
     */
    public function seedSystemRolePresets(): array
    {
        $seeded = [];
        foreach ($this->getDefaultRoleDefinitions() as $slug => $def) {
            $role = CrmRole::whereNull('tenant_id')->where('slug', $slug)->first();
            if (!$role) {
                $role = CrmRole::create([
                    'tenant_id' => null,
                    'name' => $def['name'],
                    'slug' => $slug,
                    'description' => $def['description'],
                    'is_system' => true,
                    'is_active' => true,
                    'color' => $def['color'] ?? '#4F46E5',
                ]);

                foreach ($def['permissions'] as $perm) {
                    CrmRolePermission::create([
                        'role_id' => $role->id,
                        'module' => $perm['module'],
                        'action' => $perm['action'],
                        'data_scope' => $perm['data_scope'] ?? 'all',
                    ]);
                }
            }
            $seeded[] = $role;
        }

        return $seeded;
    }

    /**
     * Master configuration of all 18 role presets.
     */
    public function getDefaultRoleDefinitions(): array
    {
        $allModules = array_keys(self::MODULES);
        $coreActions = ['view', 'create', 'edit', 'delete', 'export', 'download', 'print'];

        return [
            'org_owner' => [
                'name' => 'Organization Owner',
                'description' => 'Unrestricted command over all organization data, settings, billing, and employees.',
                'color' => '#8B5CF6',
                'permissions' => array_merge(
                    ...array_map(fn($m) => [
                        ['module' => $m, 'action' => '*', 'data_scope' => 'all']
                    ], $allModules)
                ),
            ],
            'super_admin' => [
                'name' => 'Super Admin at platform level',
                'description' => 'Root SaaS platform administrator managing tenants, global providers, and infrastructure.',
                'color' => '#EF4444',
                'permissions' => array_merge(
                    ...array_map(fn($m) => [
                        ['module' => $m, 'action' => '*', 'data_scope' => 'all']
                    ], $allModules)
                ),
            ],
            'org_admin' => [
                'name' => 'Organization Admin',
                'description' => 'Complete administrative authority across all operational modules within this workspace.',
                'color' => '#3B82F6',
                'permissions' => array_merge(
                    ...array_map(fn($m) => [
                        ['module' => $m, 'action' => '*', 'data_scope' => 'all']
                    ], array_diff($allModules, ['billing']))
                ),
            ],
            'branch_manager' => [
                'name' => 'Branch Manager',
                'description' => 'Manages operational branch staff, sales pipelines, and customer support within branch scope.',
                'color' => '#10B981',
                'permissions' => [
                    ['module' => 'leads', 'action' => '*', 'data_scope' => 'branch'],
                    ['module' => 'deals', 'action' => '*', 'data_scope' => 'branch'],
                    ['module' => 'contacts', 'action' => '*', 'data_scope' => 'branch'],
                    ['module' => 'companies', 'action' => '*', 'data_scope' => 'branch'],
                    ['module' => 'tasks', 'action' => '*', 'data_scope' => 'branch'],
                    ['module' => 'tickets', 'action' => '*', 'data_scope' => 'branch'],
                    ['module' => 'attendance', 'action' => '*', 'data_scope' => 'branch'],
                    ['module' => 'hrm', 'action' => 'view', 'data_scope' => 'branch'],
                    ['module' => 'analytics', 'action' => 'view', 'data_scope' => 'branch'],
                    ['module' => 'branches', 'action' => 'view_own', 'data_scope' => 'branch'],
                    ['module' => 'branches', 'action' => 'view_reports', 'data_scope' => 'branch'],
                    ['module' => 'branches', 'action' => 'transfer_records', 'data_scope' => 'branch'],
                    ['module' => 'branches', 'action' => 'edit', 'data_scope' => 'branch'],
                    ['module' => 'branches', 'action' => 'manage_settings', 'data_scope' => 'branch'],
                ],
            ],
            'dept_manager' => [
                'name' => 'Department Manager',
                'description' => 'Oversees departmental tasks, employees, attendance, and operational objectives.',
                'color' => '#06B6D4',
                'permissions' => [
                    ['module' => 'hrm', 'action' => 'view', 'data_scope' => 'department'],
                    ['module' => 'attendance', 'action' => '*', 'data_scope' => 'department'],
                    ['module' => 'leaves', 'action' => '*', 'data_scope' => 'department'],
                    ['module' => 'tasks', 'action' => '*', 'data_scope' => 'department'],
                    ['module' => 'projects', 'action' => '*', 'data_scope' => 'department'],
                ],
            ],
            'sales_manager' => [
                'name' => 'Sales Manager',
                'description' => 'Supervises sales pipelines, approves quotations, manages quotas, and reviews team performance.',
                'color' => '#F59E0B',
                'permissions' => [
                    ['module' => 'leads', 'action' => '*', 'data_scope' => 'team'],
                    ['module' => 'deals', 'action' => '*', 'data_scope' => 'team'],
                    ['module' => 'contacts', 'action' => '*', 'data_scope' => 'team'],
                    ['module' => 'companies', 'action' => '*', 'data_scope' => 'team'],
                    ['module' => 'quotations', 'action' => '*', 'data_scope' => 'team'],
                    ['module' => 'sales_teams', 'action' => '*', 'data_scope' => 'team'],
                    ['module' => 'analytics', 'action' => 'view', 'data_scope' => 'team'],
                    ['module' => 'whatsapp', 'action' => '*', 'data_scope' => 'team'],
                    ['module' => 'voice', 'action' => '*', 'data_scope' => 'team'],
                ],
            ],
            'sales_executive' => [
                'name' => 'Sales Executive',
                'description' => 'Engages assigned leads, creates quotations, closes deals, and conducts customer outreach.',
                'color' => '#EC4899',
                'permissions' => [
                    ['module' => 'leads', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'leads', 'action' => 'create', 'data_scope' => 'own'],
                    ['module' => 'leads', 'action' => 'edit', 'data_scope' => 'assigned'],
                    ['module' => 'deals', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'deals', 'action' => 'create', 'data_scope' => 'own'],
                    ['module' => 'deals', 'action' => 'edit', 'data_scope' => 'assigned'],
                    ['module' => 'contacts', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'companies', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'quotations', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'quotations', 'action' => 'create', 'data_scope' => 'own'],
                    ['module' => 'tasks', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'tasks', 'action' => 'create', 'data_scope' => 'own'],
                    ['module' => 'meetings', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'whatsapp', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'whatsapp', 'action' => 'create', 'data_scope' => 'assigned'],
                ],
            ],
            'accountant' => [
                'name' => 'Accountant',
                'description' => 'Controls financial operations, tax invoicing, payments reconciliation, and revenue reports.',
                'color' => '#14B8A6',
                'permissions' => [
                    ['module' => 'invoices', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'payments', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'quotations', 'action' => 'view', 'data_scope' => 'all'],
                    ['module' => 'products', 'action' => 'view', 'data_scope' => 'all'],
                    ['module' => 'procurement', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'analytics', 'action' => 'view', 'data_scope' => 'all'],
                ],
            ],
            'hr_manager' => [
                'name' => 'HR Manager',
                'description' => 'Administers workforce roster, employee records, shift scheduling, and leave approvals.',
                'color' => '#6366F1',
                'permissions' => [
                    ['module' => 'hrm', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'attendance', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'leaves', 'action' => '*', 'data_scope' => 'all'],
                ],
            ],
            'hr_executive' => [
                'name' => 'HR Executive',
                'description' => 'Assists with staff onboarding, attendance monitoring, and routine leave applications.',
                'color' => '#818CF8',
                'permissions' => [
                    ['module' => 'hrm', 'action' => 'view', 'data_scope' => 'all'],
                    ['module' => 'attendance', 'action' => 'view', 'data_scope' => 'all'],
                    ['module' => 'leaves', 'action' => 'view', 'data_scope' => 'all'],
                    ['module' => 'leaves', 'action' => 'create', 'data_scope' => 'all'],
                ],
            ],
            'marketing_manager' => [
                'name' => 'Marketing Manager',
                'description' => 'Designs marketing campaigns, manages audiences, landing pages, and lead acquisition.',
                'color' => '#D97706',
                'permissions' => [
                    ['module' => 'marketing', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'landing_pages', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'leads', 'action' => 'view', 'data_scope' => 'all'],
                    ['module' => 'analytics', 'action' => 'view', 'data_scope' => 'all'],
                ],
            ],
            'marketing_executive' => [
                'name' => 'Marketing Executive',
                'description' => 'Drafts email broadcasts, creates landing page blocks, and updates marketing assets.',
                'color' => '#FBBF24',
                'permissions' => [
                    ['module' => 'marketing', 'action' => 'view', 'data_scope' => 'all'],
                    ['module' => 'marketing', 'action' => 'create', 'data_scope' => 'own'],
                    ['module' => 'landing_pages', 'action' => 'view', 'data_scope' => 'all'],
                    ['module' => 'landing_pages', 'action' => 'create', 'data_scope' => 'own'],
                ],
            ],
            'support_manager' => [
                'name' => 'Support Manager',
                'description' => 'Supervises support queues, SLA resolution rates, escalations, and customer feedback.',
                'color' => '#059669',
                'permissions' => [
                    ['module' => 'tickets', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'omnichannel', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'whatsapp', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'analytics', 'action' => 'view', 'data_scope' => 'all'],
                ],
            ],
            'support_executive' => [
                'name' => 'Support Executive',
                'description' => 'Resolves assigned customer support tickets and assists through omnichannel messaging.',
                'color' => '#34D399',
                'permissions' => [
                    ['module' => 'tickets', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'tickets', 'action' => 'edit', 'data_scope' => 'assigned'],
                    ['module' => 'omnichannel', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'omnichannel', 'action' => 'create', 'data_scope' => 'assigned'],
                    ['module' => 'whatsapp', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'whatsapp', 'action' => 'create', 'data_scope' => 'assigned'],
                ],
            ],
            'project_manager' => [
                'name' => 'Project Manager',
                'description' => 'Oversees project delivery, task allocation, milestones, and deliverable schedules.',
                'color' => '#2563EB',
                'permissions' => [
                    ['module' => 'projects', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'tasks', 'action' => '*', 'data_scope' => 'all'],
                    ['module' => 'meetings', 'action' => '*', 'data_scope' => 'all'],
                ],
            ],
            'project_employee' => [
                'name' => 'Project Employee',
                'description' => 'Executes assigned project tasks, logs work hours, and completes milestone checklists.',
                'color' => '#60A5FA',
                'permissions' => [
                    ['module' => 'projects', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'tasks', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'tasks', 'action' => 'edit', 'data_scope' => 'assigned'],
                    ['module' => 'meetings', 'action' => 'view', 'data_scope' => 'assigned'],
                ],
            ],
            'employee' => [
                'name' => 'Employee',
                'description' => 'Standard internal workforce member with self-service attendance and assigned tasks.',
                'color' => '#6B7280',
                'permissions' => [
                    ['module' => 'attendance', 'action' => 'view', 'data_scope' => 'own'],
                    ['module' => 'attendance', 'action' => 'create', 'data_scope' => 'own'],
                    ['module' => 'leaves', 'action' => 'view', 'data_scope' => 'own'],
                    ['module' => 'leaves', 'action' => 'create', 'data_scope' => 'own'],
                    ['module' => 'tasks', 'action' => 'view', 'data_scope' => 'assigned'],
                    ['module' => 'tasks', 'action' => 'edit', 'data_scope' => 'assigned'],
                ],
            ],
            'customer_portal_user' => [
                'name' => 'Customer Portal User',
                'description' => 'External customer self-service access to authorized quotations, invoices, and tickets.',
                'color' => '#9CA3AF',
                'permissions' => [
                    ['module' => 'quotations', 'action' => 'view', 'data_scope' => 'own'],
                    ['module' => 'invoices', 'action' => 'view', 'data_scope' => 'own'],
                    ['module' => 'tickets', 'action' => 'view', 'data_scope' => 'own'],
                    ['module' => 'tickets', 'action' => 'create', 'data_scope' => 'own'],
                ],
            ],
        ];
    }
}
