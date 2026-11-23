import React, { useState, useEffect, useMemo } from 'react';
import {
  Shield,
  ShieldAlert,
  ShieldCheck,
  Plus,
  Copy,
  Trash2,
  Edit2,
  Users,
  Search,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  ChevronDown,
  ChevronRight,
  GitCompare,
  Lock,
  ArrowRight,
  RefreshCw,
  Sparkles,
  Sliders,
  Check,
  X,
  UserPlus,
  Eye,
  Settings,
  HelpCircle
} from 'lucide-react';
import {
  api,
  CrmRoleItem,
  CrmRolePermissionItem,
  RoleMatrixMetadata,
  RoleComparisonItem
} from '../../api/client';
import { useCRMStore } from '../../store/crmStore';

interface RoleManagementMatrixViewProps {
  theme?: 'light' | 'dark';
}

export default function RoleManagementMatrixView({ theme = 'dark' }: RoleManagementMatrixViewProps) {
  const { addToast } = useCRMStore();

  // State
  const [roles, setRoles] = useState<CrmRoleItem[]>([]);
  const [selectedRole, setSelectedRole] = useState<CrmRoleItem | null>(null);
  const [metadata, setMetadata] = useState<RoleMatrixMetadata | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [roleSearch, setRoleSearch] = useState<string>('');
  const [permSearch, setPermSearch] = useState<string>('');
  const [expandedModules, setExpandedModules] = useState<Record<string, boolean>>({});

  // Local unsaved permission edits: map of `${module}:${action}` -> { allowed: boolean, data_scope: string }
  const [matrixState, setMatrixState] = useState<Record<string, { allowed: boolean; data_scope: string }>>({});
  const [moduleScopes, setModuleScopes] = useState<Record<string, string>>({});
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState<boolean>(false);

  // Modals
  const [createModalOpen, setCreateModalOpen] = useState<boolean>(false);
  const [newRoleName, setNewRoleName] = useState<string>('');
  const [newRoleDesc, setNewRoleDesc] = useState<string>('');
  const [newRoleColor, setNewRoleColor] = useState<string>('#4F46E5');
  const [newRoleBaseId, setNewRoleBaseId] = useState<number | undefined>(undefined);

  const [dupModalOpen, setDupModalOpen] = useState<boolean>(false);
  const [dupRoleName, setDupRoleName] = useState<string>('');
  const [dupRoleDesc, setDupRoleDesc] = useState<string>('');

  const [compareModalOpen, setCompareModalOpen] = useState<boolean>(false);
  const [compareRoleAId, setCompareRoleAId] = useState<number | undefined>(undefined);
  const [compareRoleBId, setCompareRoleBId] = useState<number | undefined>(undefined);
  const [comparisonResults, setComparisonResults] = useState<RoleComparisonItem[] | null>(null);
  const [comparing, setComparing] = useState<boolean>(false);

  const [usersModalOpen, setUsersModalOpen] = useState<boolean>(false);
  const [assignedUsers, setAssignedUsers] = useState<any[]>([]);
  const [assignUserModalOpen, setAssignUserModalOpen] = useState<boolean>(false);
  const [selectedUserId, setSelectedUserId] = useState<number | undefined>(undefined);
  const [isPrimaryRole, setIsPrimaryRole] = useState<boolean>(true);

  // Fetch roles and metadata
  const fetchAllData = async () => {
    try {
      setLoading(true);
      const [rolesRes, metaRes] = await Promise.all([
        api.getCrmRoles(),
        api.getCrmRoleMatrixMetadata()
      ]);

      if (rolesRes.data) {
        setRoles(rolesRes.data);
        if (!selectedRole && rolesRes.data.length > 0) {
          // Default to first non-system or first role
          const initial = rolesRes.data.find(r => !r.is_system) || rolesRes.data[0];
          loadRoleDetails(initial.id);
        } else if (selectedRole) {
          loadRoleDetails(selectedRole.id);
        }
      }

      if (metaRes.data) {
        setMetadata(metaRes.data);
        // Expand first 3 modules by default
        const initialExpanded: Record<string, boolean> = {};
        Object.keys(metaRes.data.modules).slice(0, 4).forEach(m => {
          initialExpanded[m] = true;
        });
        setExpandedModules(initialExpanded);
      }
    } catch (err: any) {
      addToast(err.message || 'Failed to load roles and permissions', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAllData();
  }, []);

  // Load specific role permissions into working state
  const loadRoleDetails = async (roleId: number) => {
    try {
      const res = await api.getCrmRole(roleId);
      if (res.data) {
        setSelectedRole(res.data);
        const perms = res.data.permissions || [];

        const newMatrix: Record<string, { allowed: boolean; data_scope: string }> = {};
        const newScopes: Record<string, string> = {};

        perms.forEach(p => {
          newMatrix[`${p.module}:${p.action}`] = {
            allowed: true,
            data_scope: p.data_scope || 'all'
          };
          if (!newScopes[p.module] || p.data_scope === 'all') {
            newScopes[p.module] = p.data_scope || 'all';
          }
        });

        setMatrixState(newMatrix);
        setModuleScopes(newScopes);
        setHasUnsavedChanges(false);
      }
    } catch (err: any) {
      addToast(err.message || 'Failed to load role details', 'error');
    }
  };

  // Toggle specific action permission
  const handleToggleAction = (module: string, action: string) => {
    const key = `${module}:${action}`;
    const current = matrixState[key]?.allowed || false;
    const currentScope = matrixState[key]?.data_scope || moduleScopes[module] || 'all';

    setMatrixState(prev => ({
      ...prev,
      [key]: {
        allowed: !current,
        data_scope: currentScope
      }
    }));
    setHasUnsavedChanges(true);
  };

  // Change data scope for an entire module
  const handleModuleScopeChange = (module: string, scope: string) => {
    setModuleScopes(prev => ({ ...prev, [module]: scope }));

    // Also update all allowed actions under this module to this scope
    setMatrixState(prev => {
      const updated = { ...prev };
      Object.keys(updated).forEach(k => {
        if (k.startsWith(`${module}:`) && updated[k].allowed) {
          updated[k] = { ...updated[k], data_scope: scope };
        }
      });
      return updated;
    });
    setHasUnsavedChanges(true);
  };

  // Toggle select all actions for a module
  const handleToggleModuleAll = (module: string, selectAll: boolean) => {
    if (!metadata) return;
    const actions = Object.keys(metadata.actions);
    const scope = moduleScopes[module] || 'all';

    setMatrixState(prev => {
      const updated = { ...prev };
      actions.forEach(act => {
        const key = `${module}:${act}`;
        updated[key] = {
          allowed: selectAll,
          data_scope: scope
        };
      });
      return updated;
    });
    setHasUnsavedChanges(true);
  };

  // Global select all / deselect all
  const handleGlobalSelectAll = (selectAll: boolean) => {
    if (!metadata) return;
    const newMatrix: Record<string, { allowed: boolean; data_scope: string }> = {};

    if (selectAll) {
      Object.keys(metadata.modules).forEach(mod => {
        const scope = moduleScopes[mod] || 'all';
        Object.keys(metadata.actions).forEach(act => {
          newMatrix[`${mod}:${act}`] = { allowed: true, data_scope: scope };
        });
      });
    }

    setMatrixState(newMatrix);
    setHasUnsavedChanges(true);
  };

  // Save changes to current role
  const handleSaveChanges = async () => {
    if (!selectedRole) return;
    try {
      setSaving(true);
      const permissionsPayload: CrmRolePermissionItem[] = [];

      Object.entries(matrixState).forEach(([key, val]) => {
        if (val.allowed) {
          const [module, action] = key.split(':');
          permissionsPayload.push({
            module,
            action,
            data_scope: val.data_scope || moduleScopes[module] || 'all'
          });
        }
      });

      await api.updateCrmRole(selectedRole.id, {
        permissions: permissionsPayload
      });

      addToast(`Permissions saved for role '${selectedRole.name}'.`, 'success');
      setHasUnsavedChanges(false);
      await loadRoleDetails(selectedRole.id);
      await fetchAllData();
    } catch (err: any) {
      addToast(err.message || 'Failed to save role permissions', 'error');
    } finally {
      setSaving(false);
    }
  };

  // Create new role
  const handleCreateRole = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newRoleName) return;

    try {
      setSaving(true);
      let initialPerms: CrmRolePermissionItem[] = [];

      if (newRoleBaseId) {
        const baseRoleRes = await api.getCrmRole(newRoleBaseId);
        if (baseRoleRes.data?.permissions) {
          initialPerms = baseRoleRes.data.permissions.map(p => ({
            module: p.module,
            action: p.action,
            data_scope: p.data_scope
          }));
        }
      }

      const res = await api.createCrmRole({
        name: newRoleName,
        description: newRoleDesc,
        color: newRoleColor,
        is_active: true,
        parent_role_id: newRoleBaseId,
        permissions: initialPerms
      });

      addToast(`Custom role '${newRoleName}' created successfully.`, 'success');
      setCreateModalOpen(false);
      setNewRoleName('');
      setNewRoleDesc('');
      await fetchAllData();
      if (res.data) {
        loadRoleDetails(res.data.id);
      }
    } catch (err: any) {
      addToast(err.message || 'Failed to create role', 'error');
    } finally {
      setSaving(false);
    }
  };

  // Duplicate role
  const handleDuplicateRole = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedRole || !dupRoleName) return;

    try {
      setSaving(true);
      const res = await api.duplicateCrmRole(selectedRole.id, {
        name: dupRoleName,
        description: dupRoleDesc
      });

      addToast(`Role duplicated as '${dupRoleName}'.`, 'success');
      setDupModalOpen(false);
      setDupRoleName('');
      setDupRoleDesc('');
      await fetchAllData();
      if (res.data) {
        loadRoleDetails(res.data.id);
      }
    } catch (err: any) {
      addToast(err.message || 'Failed to duplicate role', 'error');
    } finally {
      setSaving(false);
    }
  };

  // Delete custom role
  const handleDeleteRole = async (role: CrmRoleItem) => {
    if (role.is_system) {
      addToast('System preset roles cannot be deleted.', 'error');
      return;
    }

    if (!window.confirm(`Are you sure you want to delete role '${role.name}'?`)) {
      return;
    }

    try {
      setSaving(true);
      await api.deleteCrmRole(role.id);
      addToast(`Role '${role.name}' deleted successfully.`, 'success');
      setSelectedRole(null);
      await fetchAllData();
    } catch (err: any) {
      addToast(err.message || 'Failed to delete role', 'error');
    } finally {
      setSaving(false);
    }
  };

  // Copy permissions from another role
  const handleCopyFromRole = async (sourceRoleId: number) => {
    if (!selectedRole) return;
    try {
      setSaving(true);
      await api.copyCrmRolePermissions(selectedRole.id, sourceRoleId);
      addToast('Permissions copied successfully.', 'success');
      await loadRoleDetails(selectedRole.id);
    } catch (err: any) {
      addToast(err.message || 'Failed to copy permissions', 'error');
    } finally {
      setSaving(false);
    }
  };

  // Run role comparison
  const handleRunComparison = async () => {
    if (!compareRoleAId || !compareRoleBId) return;
    try {
      setComparing(true);
      const res = await api.compareCrmRoles(compareRoleAId, compareRoleBId);
      if (res.data) {
        setComparisonResults(res.data.comparison);
      }
    } catch (err: any) {
      addToast(err.message || 'Failed to compare roles', 'error');
    } finally {
      setComparing(false);
    }
  };

  // View assigned users
  const handleOpenUsersModal = async (role: CrmRoleItem) => {
    try {
      const res = await api.getCrmRoleUsers(role.id);
      if (res.data) {
        setAssignedUsers(res.data.users);
        setUsersModalOpen(true);
      }
    } catch (err: any) {
      addToast(err.message || 'Failed to fetch assigned users', 'error');
    }
  };

  // Filtered roles list
  const filteredRoles = useMemo(() => {
    return roles.filter(r =>
      r.name.toLowerCase().includes(roleSearch.toLowerCase()) ||
      (r.description && r.description.toLowerCase().includes(roleSearch.toLowerCase()))
    );
  }, [roles, roleSearch]);

  // Filtered modules by permission search
  const filteredModules = useMemo(() => {
    if (!metadata) return [];
    const entries = Object.entries(metadata.modules);
    if (!permSearch) return entries;

    return entries.filter(([modKey, modLabel]) => {
      const matchMod = modLabel.toLowerCase().includes(permSearch.toLowerCase()) || modKey.toLowerCase().includes(permSearch.toLowerCase());
      const matchAct = Object.entries(metadata.actions).some(([actKey, actLabel]) =>
        actLabel.toLowerCase().includes(permSearch.toLowerCase()) || actKey.toLowerCase().includes(permSearch.toLowerCase())
      );
      return matchMod || matchAct;
    });
  }, [metadata, permSearch]);

  const totalAssignedActions = useMemo(() => {
    return Object.values(matrixState).filter(v => v.allowed).length;
  }, [matrixState]);

  return (
    <div className="space-y-6">
      {/* 1. Header & KPI Statistics */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2.5">
            <div className="p-2.5 rounded-xl bg-indigo-600/10 text-indigo-500 border border-indigo-500/20">
              <ShieldCheck className="h-6 w-6" />
            </div>
            <div>
              <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                Enterprise RBAC & Granular Permission Matrix
              </h1>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Manage system role presets, tenant custom roles, action permissions, and row-level data scopes.
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => {
              if (roles.length >= 2) {
                setCompareRoleAId(roles[0].id);
                setCompareRoleBId(roles[1].id);
                setCompareModalOpen(true);
              }
            }}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700 transition"
          >
            <GitCompare className="h-3.5 w-3.5 text-indigo-400" />
            Compare Roles
          </button>

          <button
            onClick={() => setCreateModalOpen(true)}
            className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-500 transition shadow-sm"
          >
            <Plus className="h-3.5 w-3.5" />
            Create Custom Role
          </button>
        </div>
      </div>

      {/* KPI Stats Bar */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div className="p-3.5 rounded-xl border border-slate-800 bg-slate-900/60 flex items-center justify-between">
          <div>
            <p className="text-[10px] uppercase font-bold tracking-wider text-slate-500">Total Defined Roles</p>
            <p className="text-lg font-bold text-slate-100 mt-0.5">{roles.length}</p>
          </div>
          <Shield className="h-5 w-5 text-indigo-400 opacity-60" />
        </div>

        <div className="p-3.5 rounded-xl border border-slate-800 bg-slate-900/60 flex items-center justify-between">
          <div>
            <p className="text-[10px] uppercase font-bold tracking-wider text-slate-500">System Presets</p>
            <p className="text-lg font-bold text-emerald-400 mt-0.5">{roles.filter(r => r.is_system).length}</p>
          </div>
          <Lock className="h-5 w-5 text-emerald-400 opacity-60" />
        </div>

        <div className="p-3.5 rounded-xl border border-slate-800 bg-slate-900/60 flex items-center justify-between">
          <div>
            <p className="text-[10px] uppercase font-bold tracking-wider text-slate-500">Custom Tenant Roles</p>
            <p className="text-lg font-bold text-amber-400 mt-0.5">{roles.filter(r => !r.is_system).length}</p>
          </div>
          <Sparkles className="h-5 w-5 text-amber-400 opacity-60" />
        </div>

        <div className="p-3.5 rounded-xl border border-slate-800 bg-slate-900/60 flex items-center justify-between">
          <div>
            <p className="text-[10px] uppercase font-bold tracking-wider text-slate-500">Active Permissions in Selected</p>
            <p className="text-lg font-bold text-indigo-400 mt-0.5">{totalAssignedActions}</p>
          </div>
          <Sliders className="h-5 w-5 text-indigo-400 opacity-60" />
        </div>
      </div>

      {/* 2. Main Dual Panel: Roles Roster + Expandable Permission Matrix */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left Column: Roles Listing */}
        <div className="lg:col-span-4 space-y-4">
          <div className="p-4 rounded-2xl border border-slate-800 bg-slate-900/90 shadow-sm space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-bold text-slate-200 flex items-center gap-2">
                <Users className="h-4 w-4 text-indigo-400" />
                Available Roles ({filteredRoles.length})
              </h2>
            </div>

            {/* Search Input */}
            <div className="relative">
              <Search className="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-500" />
              <input
                type="text"
                placeholder="Search roles..."
                value={roleSearch}
                onChange={e => setRoleSearch(e.target.value)}
                className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-950 border border-slate-800 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
              />
            </div>

            {/* Role Items List */}
            <div className="space-y-2 max-h-[640px] overflow-y-auto pr-1">
              {filteredRoles.map(role => {
                const isSelected = selectedRole?.id === role.id;
                return (
                  <div
                    key={role.id}
                    onClick={() => loadRoleDetails(role.id)}
                    className={`p-3 rounded-xl border transition cursor-pointer text-left ${
                      isSelected
                        ? 'border-indigo-500/60 bg-indigo-950/20 shadow-md ring-1 ring-indigo-500/30'
                        : 'border-slate-800/80 bg-slate-950/40 hover:bg-slate-800/40 hover:border-slate-700'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-2">
                        <span
                          className="h-2.5 w-2.5 rounded-full flex-shrink-0"
                          style={{ backgroundColor: role.color || '#4F46E5' }}
                        />
                        <span className="text-xs font-bold text-slate-200 line-clamp-1">{role.name}</span>
                      </div>

                      {role.is_system ? (
                        <span className="text-[9px] font-mono uppercase px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex-shrink-0">
                          Preset
                        </span>
                      ) : (
                        <span className="text-[9px] font-mono uppercase px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 flex-shrink-0">
                          Custom
                        </span>
                      )}
                    </div>

                    <p className="text-[11px] text-slate-400 line-clamp-2 mt-1.5 leading-relaxed">
                      {role.description || 'No description configured.'}
                    </p>

                    <div className="flex items-center justify-between mt-2.5 pt-2 border-t border-slate-800/60 text-[10px] text-slate-400">
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          handleOpenUsersModal(role);
                        }}
                        className="hover:text-indigo-400 flex items-center gap-1 font-medium transition"
                      >
                        <Users className="h-3 w-3" />
                        {role.users_count ?? 0} Users
                      </button>

                      <div className="flex items-center gap-1.5" onClick={(e) => e.stopPropagation()}>
                        <button
                          title="Duplicate Role"
                          onClick={() => {
                            setSelectedRole(role);
                            setDupRoleName(`${role.name} (Copy)`);
                            setDupModalOpen(true);
                          }}
                          className="p-1 rounded hover:bg-slate-800 text-slate-400 hover:text-slate-200 transition"
                        >
                          <Copy className="h-3 w-3" />
                        </button>

                        {!role.is_system && (
                          <button
                            title="Delete Custom Role"
                            onClick={() => handleDeleteRole(role)}
                            className="p-1 rounded hover:bg-rose-950/40 text-slate-400 hover:text-rose-400 transition"
                          >
                            <Trash2 className="h-3 w-3" />
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>

        {/* Right Column: Permission Matrix for Selected Role */}
        <div className="lg:col-span-8 space-y-4">
          {selectedRole ? (
            <div className="p-5 rounded-2xl border border-slate-800 bg-slate-900/90 shadow-sm space-y-5">
              {/* Role Header Info */}
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-800">
                <div>
                  <div className="flex items-center gap-2.5">
                    <span
                      className="h-3 w-3 rounded-full"
                      style={{ backgroundColor: selectedRole.color || '#4F46E5' }}
                    />
                    <h2 className="text-base font-bold text-slate-100">{selectedRole.name}</h2>
                    {selectedRole.is_system && (
                      <span className="text-[10px] font-mono uppercase px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        System Protected
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-slate-400 mt-1">{selectedRole.description}</p>
                </div>

                <div className="flex items-center gap-2">
                  {/* Copy Permissions From Dropdown */}
                  <select
                    onChange={(e) => {
                      if (e.target.value) {
                        handleCopyFromRole(parseInt(e.target.value, 10));
                        e.target.value = '';
                      }
                    }}
                    defaultValue=""
                    className="text-xs bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-slate-300 focus:outline-none focus:border-indigo-500"
                  >
                    <option value="" disabled>Copy permissions from...</option>
                    {roles
                      .filter(r => r.id !== selectedRole.id)
                      .map(r => (
                        <option key={r.id} value={r.id}>{r.name}</option>
                      ))}
                  </select>
                </div>
              </div>

              {/* Unsaved Changes Banner */}
              {hasUnsavedChanges && (
                <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/25 flex items-center justify-between animate-pulse">
                  <div className="flex items-center gap-2 text-xs font-semibold text-amber-300">
                    <AlertTriangle className="h-4 w-4" />
                    You have unsaved permission changes.
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => loadRoleDetails(selectedRole.id)}
                      className="px-2.5 py-1 text-xs text-slate-300 hover:text-white transition"
                    >
                      Discard
                    </button>
                    <button
                      disabled={saving}
                      onClick={handleSaveChanges}
                      className="px-3 py-1 rounded-md text-xs font-bold bg-amber-500 text-slate-950 hover:bg-amber-400 transition"
                    >
                      {saving ? 'Saving...' : 'Save Permissions'}
                    </button>
                  </div>
                </div>
              )}

              {/* Controls Toolbar: Search & Bulk Toggles */}
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-950/60 p-3 rounded-xl border border-slate-800/80">
                <div className="relative w-full sm:w-64">
                  <Search className="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-500" />
                  <input
                    type="text"
                    placeholder="Search module or action..."
                    value={permSearch}
                    onChange={e => setPermSearch(e.target.value)}
                    className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-900 border border-slate-800 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                  />
                </div>

                <div className="flex items-center gap-2">
                  <button
                    onClick={() => handleGlobalSelectAll(true)}
                    className="px-2.5 py-1 text-xs font-semibold rounded bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 hover:bg-indigo-600/30 transition"
                  >
                    Select All
                  </button>
                  <button
                    onClick={() => handleGlobalSelectAll(false)}
                    className="px-2.5 py-1 text-xs font-semibold rounded bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 transition"
                  >
                    Deselect All
                  </button>
                </div>
              </div>

              {/* Expandable Module Accordions */}
              <div className="space-y-3 max-h-[580px] overflow-y-auto pr-1">
                {filteredModules.map(([modKey, modLabel]) => {
                  const isExpanded = expandedModules[modKey] ?? false;
                  const currentScope = moduleScopes[modKey] || 'all';

                  // Count active actions in this module
                  const totalModuleActions = metadata ? Object.keys(metadata.actions).length : 20;
                  const activeModuleActions = metadata
                    ? Object.keys(metadata.actions).filter(act => matrixState[`${modKey}:${act}`]?.allowed).length
                    : 0;

                  return (
                    <div
                      key={modKey}
                      className="rounded-xl border border-slate-800 bg-slate-950/40 overflow-hidden transition"
                    >
                      {/* Accordion Header */}
                      <div
                        onClick={() =>
                          setExpandedModules(prev => ({ ...prev, [modKey]: !isExpanded }))
                        }
                        className="p-3.5 flex items-center justify-between cursor-pointer hover:bg-slate-900/60 transition"
                      >
                        <div className="flex items-center gap-3">
                          {isExpanded ? (
                            <ChevronDown className="h-4 w-4 text-slate-400" />
                          ) : (
                            <ChevronRight className="h-4 w-4 text-slate-400" />
                          )}
                          <div>
                            <span className="text-xs font-bold text-slate-200">{modLabel}</span>
                            <span className="text-[10px] text-slate-500 ml-2 font-mono">({modKey})</span>
                          </div>
                        </div>

                        <div className="flex items-center gap-3" onClick={e => e.stopPropagation()}>
                          {/* Active Count Pill */}
                          <span
                            className={`text-[10px] font-mono px-2 py-0.5 rounded-md border ${
                              activeModuleActions > 0
                                ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20'
                                : 'bg-slate-800 text-slate-500 border-slate-700'
                            }`}
                          >
                            {activeModuleActions} / {totalModuleActions} actions
                          </span>

                          {/* Data Scope Selector Dropdown */}
                          <div className="flex items-center gap-1.5">
                            <span className="text-[10px] text-slate-500 uppercase font-bold hidden sm:inline">Scope:</span>
                            <select
                              value={currentScope}
                              onChange={e => handleModuleScopeChange(modKey, e.target.value)}
                              className="text-[11px] bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-indigo-300 focus:outline-none focus:border-indigo-500"
                            >
                              <option value="all">All Org Records</option>
                              <option value="branch">Branch Only</option>
                              <option value="department">Department Only</option>
                              <option value="team">Team Only</option>
                              <option value="assigned">Assigned Only</option>
                              <option value="own">Own Created Only</option>
                            </select>
                          </div>

                          {/* Quick Toggle All in Module */}
                          <button
                            onClick={() => handleToggleModuleAll(modKey, activeModuleActions < totalModuleActions)}
                            className="text-[10px] text-indigo-400 hover:text-indigo-300 font-medium ml-1"
                          >
                            {activeModuleActions < totalModuleActions ? 'Check All' : 'Clear'}
                          </button>
                        </div>
                      </div>

                      {/* Accordion Body: 20 Action Checkboxes */}
                      {isExpanded && metadata && (
                        <div className="p-4 pt-2 border-t border-slate-800/80 bg-slate-900/30">
                          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5">
                            {Object.entries(metadata.actions).map(([actKey, actLabel]) => {
                              const key = `${modKey}:${actKey}`;
                              const isChecked = matrixState[key]?.allowed || false;

                              return (
                                <label
                                  key={actKey}
                                  className={`p-2 rounded-lg border flex items-start gap-2 cursor-pointer transition select-none ${
                                    isChecked
                                      ? 'bg-indigo-600/10 border-indigo-500/30 text-slate-200'
                                      : 'bg-slate-950/40 border-slate-800 text-slate-400 hover:border-slate-700'
                                  }`}
                                >
                                  <input
                                    type="checkbox"
                                    checked={isChecked}
                                    onChange={() => handleToggleAction(modKey, actKey)}
                                    className="mt-0.5 rounded border-slate-700 text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5 bg-slate-900"
                                  />
                                  <div className="text-left">
                                    <p className="text-[11px] font-semibold leading-tight capitalize">
                                      {actKey.replace('_', ' ')}
                                    </p>
                                    <p className="text-[9px] text-slate-500 leading-normal line-clamp-1">
                                      {actLabel}
                                    </p>
                                  </div>
                                </label>
                              );
                            })}
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>

              {/* Save Footer */}
              <div className="flex items-center justify-between pt-4 border-t border-slate-800">
                <p className="text-xs text-slate-500">
                  Permissions are cached for performance and invalidated immediately on update.
                </p>
                <button
                  disabled={saving || !hasUnsavedChanges}
                  onClick={handleSaveChanges}
                  className={`px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 ${
                    hasUnsavedChanges
                      ? 'bg-indigo-600 text-white hover:bg-indigo-500 shadow-md'
                      : 'bg-slate-800 text-slate-500 cursor-not-allowed'
                  }`}
                >
                  <Check className="h-4 w-4" />
                  {saving ? 'Saving...' : 'Save Role Permissions'}
                </button>
              </div>
            </div>
          ) : (
            <div className="p-12 rounded-2xl border border-dashed border-slate-800 flex flex-col items-center justify-center text-center">
              <Shield className="h-10 w-10 text-slate-600 mb-3" />
              <p className="text-sm font-semibold text-slate-300">Select a role from the left roster</p>
              <p className="text-xs text-slate-500 mt-1 max-w-sm">
                Choose any system role preset or custom tenant role to inspect and edit its granular permission matrix and data scopes.
              </p>
            </div>
          )}
        </div>
      </div>

      {/* 3. Modal: Create Custom Role */}
      {createModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
          <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl text-left">
            <h3 className="text-base font-bold text-slate-100 flex items-center gap-2">
              <Plus className="h-5 w-5 text-indigo-400" />
              Create Custom Role
            </h3>
            <p className="text-xs text-slate-400 mt-1">
              Define a new role tailored to your organization's departmental workflow.
            </p>

            <form onSubmit={handleCreateRole} className="mt-4 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Role Name</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Regional Territory Auditor"
                  value={newRoleName}
                  onChange={e => setNewRoleName(e.target.value)}
                  className="w-full text-xs px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Description</label>
                <textarea
                  rows={2}
                  placeholder="Briefly describe what members with this role are allowed to perform..."
                  value={newRoleDesc}
                  onChange={e => setNewRoleDesc(e.target.value)}
                  className="w-full text-xs px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Base Template (Optional)</label>
                <select
                  value={newRoleBaseId || ''}
                  onChange={e => setNewRoleBaseId(e.target.value ? parseInt(e.target.value, 10) : undefined)}
                  className="w-full text-xs px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-indigo-500"
                >
                  <option value="">Start Empty (No initial permissions)</option>
                  {roles.map(r => (
                    <option key={r.id} value={r.id}>
                      Clone initial permissions from: {r.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Badge Color</label>
                <div className="flex items-center gap-2">
                  {['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#EC4899', '#8B5CF6', '#06B6D4'].map(color => (
                    <button
                      key={color}
                      type="button"
                      onClick={() => setNewRoleColor(color)}
                      className={`h-6 w-6 rounded-full transition flex items-center justify-center ${
                        newRoleColor === color ? 'ring-2 ring-white ring-offset-2 ring-offset-slate-900' : ''
                      }`}
                      style={{ backgroundColor: color }}
                    >
                      {newRoleColor === color && <Check className="h-3 w-3 text-white" />}
                    </button>
                  ))}
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setCreateModalOpen(false)}
                  className="px-3 py-1.5 text-xs text-slate-400 hover:text-white transition"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={saving || !newRoleName}
                  className="px-4 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 text-white hover:bg-indigo-500 transition"
                >
                  {saving ? 'Creating...' : 'Create Role'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 4. Modal: Duplicate Role */}
      {dupModalOpen && selectedRole && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
          <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl text-left">
            <h3 className="text-base font-bold text-slate-100 flex items-center gap-2">
              <Copy className="h-5 w-5 text-indigo-400" />
              Duplicate Role '{selectedRole.name}'
            </h3>

            <form onSubmit={handleDuplicateRole} className="mt-4 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">New Role Name</label>
                <input
                  type="text"
                  required
                  value={dupRoleName}
                  onChange={e => setDupRoleName(e.target.value)}
                  className="w-full text-xs px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Description</label>
                <textarea
                  rows={2}
                  value={dupRoleDesc}
                  onChange={e => setDupRoleDesc(e.target.value)}
                  className="w-full text-xs px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setDupModalOpen(false)}
                  className="px-3 py-1.5 text-xs text-slate-400 hover:text-white transition"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={saving || !dupRoleName}
                  className="px-4 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 text-white hover:bg-indigo-500 transition"
                >
                  {saving ? 'Duplicating...' : 'Duplicate Role'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 5. Modal: Compare Roles Side-by-Side */}
      {compareModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
          <div className="w-full max-w-4xl bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl max-h-[90vh] flex flex-col text-left">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <h3 className="text-base font-bold text-slate-100 flex items-center gap-2">
                <GitCompare className="h-5 w-5 text-indigo-400" />
                Compare Roles Side-by-Side
              </h3>
              <button
                onClick={() => setCompareModalOpen(false)}
                className="text-slate-400 hover:text-white transition"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="grid grid-cols-2 gap-4 my-4">
              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Role A</label>
                <select
                  value={compareRoleAId}
                  onChange={e => setCompareRoleAId(parseInt(e.target.value, 10))}
                  className="w-full text-xs px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-indigo-500"
                >
                  {roles.map(r => (
                    <option key={r.id} value={r.id}>{r.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Role B</label>
                <select
                  value={compareRoleBId}
                  onChange={e => setCompareRoleBId(parseInt(e.target.value, 10))}
                  className="w-full text-xs px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-indigo-500"
                >
                  {roles.map(r => (
                    <option key={r.id} value={r.id}>{r.name}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="flex justify-end mb-3">
              <button
                disabled={comparing}
                onClick={handleRunComparison}
                className="px-4 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition"
              >
                {comparing ? 'Comparing...' : 'Run Comparison'}
              </button>
            </div>

            {/* Comparison Results Table */}
            <div className="flex-1 overflow-y-auto border border-slate-800 rounded-xl">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-950 border-b border-slate-800 text-[10px] text-slate-400 uppercase tracking-wider sticky top-0">
                  <tr>
                    <th className="p-3">Module</th>
                    <th className="p-3">Action</th>
                    <th className="p-3">Role A</th>
                    <th className="p-3">Role B</th>
                    <th className="p-3 text-center">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60 font-mono text-[11px]">
                  {comparisonResults ? (
                    comparisonResults.map((row, idx) => (
                      <tr
                        key={idx}
                        className={row.is_different ? 'bg-amber-500/5 hover:bg-amber-500/10' : 'hover:bg-slate-800/30'}
                      >
                        <td className="p-3 font-semibold text-slate-300">{row.module}</td>
                        <td className="p-3 text-slate-400">{row.action}</td>
                        <td className="p-3">
                          {row.role_a_has ? (
                            <span className="text-emerald-400">Granted ({row.role_a_scope})</span>
                          ) : (
                            <span className="text-slate-600">Denied</span>
                          )}
                        </td>
                        <td className="p-3">
                          {row.role_b_has ? (
                            <span className="text-emerald-400">Granted ({row.role_b_scope})</span>
                          ) : (
                            <span className="text-slate-600">Denied</span>
                          )}
                        </td>
                        <td className="p-3 text-center">
                          {row.is_different ? (
                            <span className="px-1.5 py-0.5 rounded text-[9px] bg-amber-500/10 text-amber-400 border border-amber-500/20">
                              Different
                            </span>
                          ) : (
                            <span className="px-1.5 py-0.5 rounded text-[9px] bg-slate-800 text-slate-500">
                              Identical
                            </span>
                          )}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={5} className="p-8 text-center text-slate-500">
                        Click 'Run Comparison' above to compare permissions and data scopes.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* 6. Modal: View & Manage Assigned Users */}
      {usersModalOpen && selectedRole && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
          <div className="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl text-left">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div>
                <h3 className="text-base font-bold text-slate-100 flex items-center gap-2">
                  <Users className="h-5 w-5 text-indigo-400" />
                  Users with role '{selectedRole.name}'
                </h3>
                <p className="text-xs text-slate-400 mt-0.5">{assignedUsers.length} employees currently assigned</p>
              </div>
              <button
                onClick={() => setUsersModalOpen(false)}
                className="text-slate-400 hover:text-white transition"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="my-4 space-y-2 max-h-72 overflow-y-auto">
              {assignedUsers.length > 0 ? (
                assignedUsers.map(u => (
                  <div
                    key={u.id}
                    className="p-2.5 rounded-xl border border-slate-800 bg-slate-950/60 flex items-center justify-between"
                  >
                    <div>
                      <p className="text-xs font-bold text-slate-200">{u.name}</p>
                      <p className="text-[10px] text-slate-400">{u.email} • {u.department || 'Operations'}</p>
                    </div>

                    <button
                      onClick={async () => {
                        try {
                          await api.removeCrmUserRole({ user_id: u.id, role_id: selectedRole.id });
                          addToast(`Removed role from ${u.name}`, 'success');
                          handleOpenUsersModal(selectedRole);
                          fetchAllData();
                        } catch (err: any) {
                          addToast(err.message, 'error');
                        }
                      }}
                      className="px-2 py-1 text-[10px] font-semibold text-rose-400 hover:bg-rose-950/40 rounded transition"
                    >
                      Unassign
                    </button>
                  </div>
                ))
              ) : (
                <div className="p-8 text-center text-slate-500 text-xs">
                  No users are directly assigned to this role.
                </div>
              )}
            </div>

            <div className="pt-3 border-t border-slate-800 flex justify-end">
              <button
                onClick={() => setUsersModalOpen(false)}
                className="px-4 py-1.5 text-xs font-bold bg-slate-800 text-slate-200 rounded-lg hover:bg-slate-700 transition"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
