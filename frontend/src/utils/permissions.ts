/**
 * Enterprise AI CRM SaaS - Permission & RBAC Helper System
 * Frontend guardrails mirroring Laravel Policies
 */

export type UserRole =
  | 'Platform Super Admin'
  | 'Business Owner'
  | 'Business Admin'
  | 'Manager'
  | 'Sales Executive'
  | 'Support Executive'
  | 'HR'
  | 'Accountant'
  | 'Employee'
  | string;

export interface PermissionCheckUser {
  id?: string | number;
  role?: UserRole;
  is_platform_admin?: boolean;
  effective_permissions?: Record<string, string[]>;
  crm_roles?: Array<{ id: number; name: string; slug: string }>;
}

// Module access matrix by role
const ROLE_MODULE_PERMISSIONS: Record<string, string[]> = {
  'Platform Super Admin': ['*'],
  'Super Admin': ['*'],
  'Admin': ['*'],
  'org_owner': ['*'],
  'org_admin': ['*'],
  'super_admin': ['*'],
  'Business Owner': ['*'],
  'Business Admin': ['*'],
  'Manager': [
    'dashboard',
    'leads',
    'lead_acquisition',
    'landing_pages',
    'workflows',
    'marketing',
    'whatsapp',
    'whatsapp_ai',
    'voice',
    'inbox',
    'deals',
    'sales_teams',
    'customers',
    'companies',
    'contacts',
    'products',
    'quotations',
    'invoices',
    'projects',
    'tasks',
    'meetings',
    'employees',
    'tickets',
    'audit',
  ],
  'Sales Executive': [
    'dashboard',
    'leads',
    'lead_acquisition',
    'landing_pages',
    'workflows',
    'marketing',
    'whatsapp',
    'whatsapp_ai',
    'voice',
    'inbox',
    'deals',
    'sales_teams',
    'customers',
    'companies',
    'contacts',
    'products',
    'quotations',
    'tasks',
    'meetings',
  ],
  'Support Executive': [
    'dashboard',
    'inbox',
    'whatsapp',
    'customers',
    'contacts',
    'tickets',
    'tasks',
    'meetings',
  ],
  'HR': [
    'dashboard',
    'employees',
    'tasks',
    'meetings',
    'audit',
  ],
  'Accountant': [
    'dashboard',
    'customers',
    'products',
    'quotations',
    'invoices',
    'billing',
    'tasks',
  ],
  'Employee': [
    'dashboard',
    'tasks',
    'meetings',
    'tickets',
  ],
};

// Disallowed delete actions per role
const RESTRICTED_DELETE_ROLES: string[] = ['Employee', 'Support Executive', 'Accountant'];

/**
 * Checks whether user can view a given module
 */
export function canAccessModule(user: PermissionCheckUser | null | undefined, module: string): boolean {
  if (!user || !user.role) return false;

  // Platform Super Admin Console is strictly restricted to platform admins
  if (module === 'platform_console') {
    return Boolean(user.is_platform_admin || user.role === 'Platform Super Admin');
  }

  if (user.is_platform_admin) return true;

  // Support dynamic effective permissions if present
  if (user.effective_permissions) {
    if (user.effective_permissions['*']) return true;
    if (user.effective_permissions[module] && user.effective_permissions[module].length > 0) return true;
  }

  const allowedModules = ROLE_MODULE_PERMISSIONS[user.role];
  if (!allowedModules) return false;
  if (allowedModules.includes('*') || allowedModules.includes(module)) return true;

  return false;
}

/**
 * Checks whether user can perform create/update/delete action on a module
 */
export function canPerformAction(
  user: PermissionCheckUser | null | undefined,
  module: string,
  action: 'create' | 'update' | 'delete' | 'export' | 'import'
): boolean {
  if (!user || !user.role) return false;
  if (user.is_platform_admin) return true;

  // Check dynamic effective permissions if available
  if (user.effective_permissions) {
    if (user.effective_permissions['*']) return true;
    const moduleActions = user.effective_permissions[module];
    if (moduleActions) {
      if (moduleActions.includes('*') || moduleActions.includes(action)) return true;
      if (action === 'update' && moduleActions.includes('edit')) return true;
    }
  }

  // First must have module access
  if (!canAccessModule(user, module)) return false;

  // Platform Admin, Business Owner and Org Admin have full access
  if (['Business Owner', 'Business Admin', 'Super Admin', 'Admin', 'org_owner', 'org_admin', 'super_admin'].includes(user.role)) return true;

  // Delete restrictions
  if (action === 'delete') {
    if (RESTRICTED_DELETE_ROLES.includes(user.role)) return false;
    // Sales Executive can only delete their own leads/deals (frontend permits if module matches)
    if (user.role === 'Sales Executive' && !['leads', 'deals'].includes(module)) return false;
  }

  // Export permissions: require Manager or above, or Accountant for financial modules
  if (action === 'export') {
    if (['Manager', 'Business Admin', 'Business Owner', 'Super Admin', 'Admin', 'org_owner', 'org_admin', 'super_admin'].includes(user.role)) return true;
    if (user.role === 'Accountant' && ['invoices', 'quotations', 'products'].includes(module)) return true;
    if (user.role === 'Sales Executive' && ['leads', 'contacts'].includes(module)) return true;
    return false;
  }

  // Import permissions: strictly Business Admin, Business Owner, or Platform Super Admin
  if (action === 'import') {
    return ['Business Admin', 'Business Owner', 'Super Admin', 'Admin', 'org_owner', 'org_admin', 'super_admin', 'Platform Super Admin'].includes(user.role);
  }

  return true;
}

/**
 * Checks if user is permitted to manage workspace settings
 */
export function canManageSettings(user: PermissionCheckUser | null | undefined): boolean {
  if (!user || !user.role) return false;
  return user.is_platform_admin || ['Business Owner', 'Business Admin', 'Super Admin', 'Admin', 'org_owner', 'org_admin', 'super_admin'].includes(user.role);
}

/**
 * Checks if user is permitted to invite team members
 */
export function canInviteMembers(user: PermissionCheckUser | null | undefined): boolean {
  if (!user || !user.role) return false;
  return user.is_platform_admin || ['Business Owner', 'Business Admin', 'Super Admin', 'Admin', 'org_owner', 'org_admin', 'super_admin'].includes(user.role);
}
