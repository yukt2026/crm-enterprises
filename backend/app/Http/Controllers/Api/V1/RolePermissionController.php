<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\AuditSecurity\Services\AuditLogger;
use App\Domains\Identity\Models\CrmRole;
use App\Domains\Identity\Services\RbacService;
use App\Domains\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    public function __construct(
        protected RbacService $rbacService
    ) {}

    /**
     * List all available roles for the current tenant (system presets + tenant custom roles).
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;

        // Ensure system role presets are seeded
        $this->rbacService->seedSystemRolePresets();

        $roles = CrmRole::forTenant($tenantId)
            ->withCount(['permissions', 'users' => function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('crm_user_roles.tenant_id', $tenantId);
                }
            }])
            ->orderBy('is_system', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
            'meta' => [
                'total_roles' => $roles->count(),
                'system_roles' => $roles->where('is_system', true)->count(),
                'custom_roles' => $roles->where('is_system', false)->count(),
            ],
        ]);
    }

    /**
     * Get module, action, and data scope metadata for constructing the permission matrix UI.
     */
    public function matrixMetadata(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'modules' => RbacService::MODULES,
                'actions' => RbacService::ACTIONS,
                'data_scopes' => RbacService::DATA_SCOPES,
            ],
        ]);
    }

    /**
     * Normalize permissions payload allowing both {module, action} and {module, actions: []} formats.
     */
    protected function normalizePermissionsPayload(?array $permissions): array
    {
        if (empty($permissions)) {
            return [];
        }

        $normalized = [];
        foreach ($permissions as $item) {
            $module = $item['module'] ?? '';
            $dataScope = $item['data_scope'] ?? 'all';
            $constraints = $item['scope_constraints'] ?? null;

            if (isset($item['actions']) && is_array($item['actions'])) {
                foreach ($item['actions'] as $act) {
                    $normalized[] = [
                        'module' => $module,
                        'action' => $act,
                        'data_scope' => $dataScope,
                        'scope_constraints' => $constraints,
                    ];
                }
            } elseif (isset($item['action'])) {
                $normalized[] = [
                    'module' => $module,
                    'action' => $item['action'],
                    'data_scope' => $dataScope,
                    'scope_constraints' => $constraints,
                ];
            }
        }

        return $normalized;
    }

    /**
     * Create a new custom role with granular permissions and data scopes.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;
        $tenant = Tenant::findOrFail($tenantId);

        if ($request->has('permissions') && is_array($request->input('permissions'))) {
            $request->merge([
                'permissions' => $this->normalizePermissionsPayload($request->input('permissions')),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'parent_role_id' => ['nullable', 'exists:crm_roles,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*.module' => ['required_with:permissions', 'string'],
            'permissions.*.action' => ['required_with:permissions', 'string'],
            'permissions.*.data_scope' => ['nullable', 'string'],
            'permissions.*.scope_constraints' => ['nullable', 'array'],
        ]);

        $validated['created_by_user_id'] = $request->user()->id;

        $role = $this->rbacService->createCustomRole($tenant, $validated, $validated['permissions'] ?? []);

        // Record audit trail
        AuditLogger::log(
            'role_created',
            $role,
            [],
            ['name' => $role->name, 'permissions_count' => count($validated['permissions'] ?? [])]
        );

        return response()->json([
            'success' => true,
            'message' => 'Custom role created successfully.',
            'data' => $role,
        ], 201);
    }

    /**
     * Show single role and its configured permissions.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;
        $role = CrmRole::forTenant($tenantId)->with('permissions')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $role,
        ]);
    }

    /**
     * Update an existing role and its permission matrix.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;
        $role = CrmRole::forTenant($tenantId)->findOrFail($id);

        if ($request->has('permissions') && is_array($request->input('permissions'))) {
            $request->merge([
                'permissions' => $this->normalizePermissionsPayload($request->input('permissions')),
            ]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*.module' => ['required_with:permissions', 'string'],
            'permissions.*.action' => ['required_with:permissions', 'string'],
            'permissions.*.data_scope' => ['nullable', 'string'],
            'permissions.*.scope_constraints' => ['nullable', 'array'],
        ]);

        $oldName = $role->name;
        $updated = $this->rbacService->updateRole($role, $validated, $validated['permissions'] ?? null);

        // Record audit trail
        AuditLogger::log(
            'role_updated',
            $updated,
            ['old_name' => $oldName],
            ['new_name' => $updated->name, 'is_active' => $updated->is_active]
        );

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Duplicate / clone an existing role.
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;
        $sourceRole = CrmRole::forTenant($tenantId)->with('permissions')->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $newRole = $this->rbacService->duplicateRole($sourceRole, $validated['name'], $validated['description'] ?? null, $tenantId);

        AuditLogger::log(
            'role_duplicated',
            $newRole,
            ['source_role_id' => $sourceRole->id],
            ['new_role_id' => $newRole->id]
        );

        return response()->json([
            'success' => true,
            'message' => "Role duplicated as '{$newRole->name}' successfully.",
            'data' => $newRole,
        ], 201);
    }

    /**
     * Copy permissions from another role.
     */
    public function copyFrom(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;
        $targetRole = CrmRole::forTenant($tenantId)->findOrFail($id);

        $validated = $request->validate([
            'source_role_id' => ['required', 'exists:crm_roles,id'],
        ]);

        $sourceRole = CrmRole::forTenant($tenantId)->with('permissions')->findOrFail($validated['source_role_id']);

        $this->rbacService->copyPermissions($sourceRole, $targetRole);

        AuditLogger::log(
            'role_permissions_copied',
            $targetRole,
            ['source_role_id' => $sourceRole->id]
        );

        return response()->json([
            'success' => true,
            'message' => "Permissions copied from '{$sourceRole->name}' successfully.",
            'data' => $targetRole->fresh(['permissions']),
        ]);
    }

    /**
     * Compare permissions between two roles side-by-side.
     */
    public function compare(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;

        $validated = $request->validate([
            'role_a_id' => ['required', 'exists:crm_roles,id'],
            'role_b_id' => ['required', 'exists:crm_roles,id'],
        ]);

        $roleA = CrmRole::forTenant($tenantId)->with('permissions')->findOrFail($validated['role_a_id']);
        $roleB = CrmRole::forTenant($tenantId)->with('permissions')->findOrFail($validated['role_b_id']);

        $diff = $this->rbacService->compareRoles($roleA, $roleB);

        return response()->json([
            'success' => true,
            'data' => [
                'role_a' => $roleA,
                'role_b' => $roleB,
                'comparison' => $diff,
            ],
        ]);
    }

    /**
     * List all users assigned to a specific role.
     */
    public function roleUsers(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;
        $role = CrmRole::forTenant($tenantId)->findOrFail($id);

        $users = $role->users()
            ->wherePivot('tenant_id', $tenantId)
            ->select('users.id', 'users.name', 'users.email', 'users.avatar', 'users.status', 'users.department')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'users' => $users,
            ],
        ]);
    }

    /**
     * Assign a role to a user.
     */
    public function assignUser(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_id' => ['required', 'exists:crm_roles,id'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $role = CrmRole::forTenant($tenantId)->findOrFail($validated['role_id']);

        $this->rbacService->assignUserRole($targetUser, $role, $validated['is_primary'] ?? false, $request->user()->id);

        // Also sync legacy string column for backward compatibility
        if ($validated['is_primary'] ?? false) {
            $targetUser->update(['role' => $role->name]);
        }

        AuditLogger::log(
            'role_assigned',
            $targetUser,
            [],
            ['role_id' => $role->id, 'role_name' => $role->name]
        );

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' assigned to {$targetUser->name} successfully.",
        ]);
    }

    /**
     * Remove role assignment from user.
     */
    public function removeUser(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_id' => ['required', 'exists:crm_roles,id'],
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $role = CrmRole::forTenant($tenantId)->findOrFail($validated['role_id']);

        $this->rbacService->removeUserRole($targetUser, $role);

        AuditLogger::log(
            'role_removed',
            $targetUser,
            ['role_id' => $role->id, 'role_name' => $role->name]
        );

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' removed from {$targetUser->name} successfully.",
        ]);
    }

    /**
     * Safely delete custom role.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;
        $role = CrmRole::forTenant($tenantId)->findOrFail($id);

        try {
            $this->rbacService->deleteRole($role);

            AuditLogger::log(
                'role_deleted',
                null,
                ['role_id' => $id, 'role_name' => $role->name]
            );

            return response()->json([
                'success' => true,
                'message' => "Role '{$role->name}' deleted successfully.",
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get effective permissions for current user or specified user.
     */
    public function effectivePermissions(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->current_tenant_id;
        $targetUserId = $request->query('user_id', $request->user()->id);

        $targetUser = User::findOrFail($targetUserId);
        $permissions = $this->rbacService->getEffectivePermissions($targetUser, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }
}
