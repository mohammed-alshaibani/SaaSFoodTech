'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useEcho } from '@/hooks/useEcho';

// ─── Permission groupings for display ────────────────────────────────────────
const PERMISSION_GROUPS = [
    {
        label: 'Service Requests',
        icon: '📋',
        perms: ['request.create', 'request.accept', 'request.complete', 'request.view_all', 'request.view_nearby'],
    },
    {
        label: 'User Management',
        icon: '👥',
        perms: ['user.manage', 'user.view.any', 'user.grant.permissions'],
    },
    {
        label: 'Roles & Permissions',
        icon: '🔐',
        perms: ['role.view', 'role.create', 'role.update', 'role.delete', 'role.hierarchy.manage'],
    },
    {
        label: 'Permissions Admin',
        icon: '🔑',
        perms: ['permission.view', 'permission.create', 'permission.update', 'permission.delete'],
    },
    {
        label: 'System',
        icon: '⚙️',
        perms: ['system.logs'],
    },
];

// ─── Toggle Switch ────────────────────────────────────────────────────────────
function PermToggle({ checked, onChange, disabled, loading }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            disabled={disabled || loading}
            onClick={onChange}
            className={`
                relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent
                transition-all duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2
                disabled:opacity-50 disabled:cursor-not-allowed
                ${checked ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600'}
            `}
        >
            <span
                className={`
                    pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0
                    transition-transform duration-200 ease-in-out
                    ${checked ? 'translate-x-4' : 'translate-x-0'}
                `}
            />
        </button>
    );
}

// ─── User Row ─────────────────────────────────────────────────────────────────
function UserPermissionRow({ user, allPerms, onToggle, pendingMap }) {
    const effectivePerms = new Set(user.permissions ?? []);

    return (
        <tr className="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
            <td className="py-4 px-4 sticky left-0 bg-white dark:bg-slate-900 border-r border-slate-100 dark:border-slate-800 min-w-[200px]">
                <div className="flex items-center gap-3">
                    <div className="h-9 w-9 rounded-full bg-gradient-to-br from-indigo-400 to-purple-600 flex items-center justify-center text-white text-sm font-bold shrink-0">
                        {user.name?.charAt(0)?.toUpperCase()}
                    </div>
                    <div className="min-w-0">
                        <p className="font-semibold text-slate-900 dark:text-slate-100 truncate text-sm">{user.name}</p>
                        <p className="text-xs text-slate-400 truncate">{user.email}</p>
                        <span className="inline-block mt-0.5 px-1.5 py-0.5 text-[10px] rounded bg-slate-100 dark:bg-slate-800 text-slate-500 font-medium">
                            {user.roles?.[0] ?? 'no role'}
                        </span>
                    </div>
                </div>
            </td>
            {allPerms.map((perm) => {
                const isGranted = effectivePerms.has(perm);
                const isPending = Boolean(pendingMap[`${user.id}:${perm}`]);
                return (
                    <td key={perm} className="py-4 px-2 text-center">
                        <PermToggle
                            checked={isGranted}
                            onChange={() => onToggle(user, perm, !isGranted)}
                            loading={isPending}
                        />
                    </td>
                );
            })}
        </tr>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function PermissionControlCenter() {
    const { user: adminUser } = useAuth();

    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [pending, setPending] = useState({});  // key: `userId:perm` → true
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState('all');

    const showToast = useCallback((msg, type = 'success') => {
        setToast({ msg, type });
        setTimeout(() => setToast(null), 3500);
    }, []);

    // ── Fetch all users with their permissions ────────────────────────────────
    const fetchUsers = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await api.get('/admin/users', { params: { per_page: 100 } });
            setUsers(res.data.data ?? []);
        } catch {
            setError('Failed to load users. Ensure you are logged in as Admin.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { fetchUsers(); }, [fetchUsers]);

    // ── Live-update users list when an admin on another session makes changes ─
    useEcho(
        'service-requests',
        'UserPermissionsUpdated',
        () => fetchUsers(),
        []
    );

    // ── Toggle a permission via API ───────────────────────────────────────────
    const handleToggle = useCallback(async (targetUser, permission, grant) => {
        const key = `${targetUser.id}:${permission}`;
        setPending(p => ({ ...p, [key]: true }));

        try {
            if (grant) {
                await api.post(`/users/${targetUser.id}/permissions`, { permission });
            } else {
                await api.delete(`/users/${targetUser.id}/permissions/${permission}`);
            }

            // Optimistic local update
            setUsers(prev => prev.map(u => {
                if (u.id !== targetUser.id) return u;
                const perms = new Set(u.permissions ?? []);
                grant ? perms.add(permission) : perms.delete(permission);
                return { ...u, permissions: [...perms] };
            }));

            showToast(
                grant
                    ? `✓ Granted ${permission} to ${targetUser.name}`
                    : `✓ Revoked ${permission} from ${targetUser.name}`
            );
        } catch (err) {
            showToast(err.response?.data?.message ?? 'Permission update failed.', 'error');
        } finally {
            setPending(p => { const n = { ...p }; delete n[key]; return n; });
        }
    }, [showToast]);

    // ── Derived data ──────────────────────────────────────────────────────────
    const allPerms = useMemo(() => PERMISSION_GROUPS.flatMap(g => g.perms), []);

    const filteredUsers = useMemo(() => {
        return users.filter(u => {
            const matchSearch = search === '' ||
                u.name?.toLowerCase().includes(search.toLowerCase()) ||
                u.email?.toLowerCase().includes(search.toLowerCase());
            const matchRole = roleFilter === 'all' || (u.roles?.[0] ?? '') === roleFilter;
            return matchSearch && matchRole;
        });
    }, [users, search, roleFilter]);

    const uniqueRoles = useMemo(() =>
        ['all', ...new Set(users.map(u => u.roles?.[0]).filter(Boolean))], [users]);

    // ── Render ────────────────────────────────────────────────────────────────
    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 p-6 md:p-10">

            {/* Header */}
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    🔐 Permission Control Center
                </h1>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Manage granular permissions for every user. Changes take effect immediately.
                </p>
            </div>

            {/* Toast */}
            {toast && (
                <div className={`
                    fixed top-5 end-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium
                    transition-all duration-300 animate-in slide-in-from-top-2
                    ${toast.type === 'error'
                        ? 'bg-red-50 text-red-700 border border-red-200'
                        : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}
                `}>
                    {toast.msg}
                </div>
            )}

            {/* Filters */}
            <div className="flex flex-wrap gap-3 mb-6">
                <input
                    id="perm-search"
                    type="text"
                    placeholder="Search users..."
                    value={search}
                    onChange={e => setSearch(e.target.value)}
                    className="flex-1 min-w-[200px] px-4 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-700
                               bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100
                               placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <select
                    id="perm-role-filter"
                    value={roleFilter}
                    onChange={e => setRoleFilter(e.target.value)}
                    className="px-4 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-700
                               bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100
                               focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    {uniqueRoles.map(r => (
                        <option key={r} value={r}>{r === 'all' ? 'All Roles' : r}</option>
                    ))}
                </select>
                <button
                    onClick={fetchUsers}
                    className="px-4 py-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 transition"
                >
                    ↻ Refresh
                </button>
            </div>

            {/* Error */}
            {error && (
                <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                    {error}
                </div>
            )}

            {/* Permission Group Legend */}
            <div className="flex flex-wrap gap-2 mb-4">
                {PERMISSION_GROUPS.map(g => (
                    <div key={g.label} className="flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-full text-xs">
                        <span>{g.icon}</span>
                        <span className="text-slate-600 dark:text-slate-400 font-medium">{g.label}</span>
                    </div>
                ))}
            </div>

            {/* Table */}
            {loading ? (
                <div className="flex items-center justify-center py-24 text-slate-400">
                    <div className="animate-spin rounded-full h-10 w-10 border-4 border-indigo-500 border-t-transparent" />
                </div>
            ) : (
                <div className="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <table className="min-w-full bg-white dark:bg-slate-900 text-sm">
                        <thead className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
                            <tr>
                                <th className="text-start py-4 px-4 sticky left-0 bg-slate-50 dark:bg-slate-800/50 border-r border-slate-100 dark:border-slate-800 font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider text-xs min-w-[200px]">
                                    User
                                </th>
                                {PERMISSION_GROUPS.map(group => (
                                    group.perms.map((perm, i) => (
                                        <th key={perm} className="py-3 px-2 text-center min-w-[80px]">
                                            {i === 0 && (
                                                <div className="text-[10px] text-slate-400 uppercase tracking-wider mb-1 whitespace-nowrap">
                                                    {group.icon} {group.label}
                                                </div>
                                            )}
                                            <div className="text-[10px] font-medium text-slate-500 dark:text-slate-400 break-all">
                                                {perm.split('.').pop()}
                                            </div>
                                        </th>
                                    ))
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {filteredUsers.length === 0 ? (
                                <tr>
                                    <td colSpan={allPerms.length + 1} className="text-center py-16 text-slate-400">
                                        No users match the current filter.
                                    </td>
                                </tr>
                            ) : (
                                filteredUsers.map(u => (
                                    <UserPermissionRow
                                        key={u.id}
                                        user={u}
                                        allPerms={allPerms}
                                        onToggle={handleToggle}
                                        pendingMap={pending}
                                    />
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Footer count */}
            {!loading && (
                <p className="mt-4 text-xs text-slate-400 text-end">
                    Showing {filteredUsers.length} of {users.length} users
                </p>
            )}
        </div>
    );
}
