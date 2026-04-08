'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';

// ─── sub-components ───────────────────────────────────────────────────────────

function Badge({ plan }) {
    const base = 'px-2 py-0.5 rounded-full text-xs font-semibold';
    return plan === 'paid'
        ? <span className={`${base} bg-emerald-100 text-emerald-700`}>Paid</span>
        : <span className={`${base} bg-gray-100 text-gray-600`}>Free</span>;
}

function StatCard({ title, value, subtext, color = 'blue' }) {
    const colors = {
        blue: 'border-blue-200 bg-blue-50 text-blue-700',
        green: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        amber: 'border-amber-200 bg-amber-50 text-amber-700',
        purple: 'border-purple-200 bg-purple-50 text-purple-700',
    };
    return (
        <div className={`p-4 rounded-xl border ${colors[color]} space-y-1`}>
            <p className="text-xs font-medium uppercase tracking-wider opacity-70">{title}</p>
            <div className="flex items-baseline gap-2">
                <p className="text-2xl font-bold">{value}</p>
                {subtext && <p className="text-xs opacity-60">{subtext}</p>}
            </div>
        </div>
    );
}

function PermissionModal({ user, allPermissions, onClose, onSave }) {
    const current = user.permissions ?? [];
    const [selected, setSelected] = useState(new Set(current));

    const toggle = (perm) =>
        setSelected(prev => {
            const next = new Set(prev);
            next.has(perm) ? next.delete(perm) : next.add(perm);
            return next;
        });

    return (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 className="text-lg font-bold">
                    Permissions — <span className="text-blue-600">{user.name}</span>
                </h3>
                <p className="text-xs text-gray-500">
                    These are <strong>direct</strong> permission overrides on top of role defaults.
                </p>
                <div className="grid grid-cols-2 gap-2 max-h-60 overflow-y-auto pr-2">
                    {allPermissions.map(perm => (
                        <label key={perm} className="flex items-center gap-2 text-sm cursor-pointer p-1 hover:bg-gray-50 rounded">
                            <input
                                type="checkbox"
                                checked={selected.has(perm)}
                                onChange={() => toggle(perm)}
                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <code className="text-xs">{perm}</code>
                        </label>
                    ))}
                </div>
                <div className="flex justify-end gap-2 pt-4 border-t">
                    <button onClick={onClose} className="px-4 py-2 rounded-md border text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button
                        onClick={() => onSave(user.id, [...selected])}
                        className="px-4 py-2 rounded-md bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition"
                    >
                        Save Permissions
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── main component ───────────────────────────────────────────────────────────

export default function AdminDashboard() {
    const [users, setUsers] = useState([]);
    const [stats, setStats] = useState(null);
    const [allPermissions, setAllPermissions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [statsLoading, setStatsLoading] = useState(true);
    const [toast, setToast] = useState('');
    const [selectedUser, setSelectedUser] = useState(null);
    const [currentPage, setCurrentPage] = useState(1);
    const [meta, setMeta] = useState(null);

    // ── data fetching ─────────────────────────────────────────

    const fetchUsers = useCallback(async (page = 1) => {
        setLoading(true);
        try {
            const res = await api.get(`/admin/users?page=${page}`);
            setUsers(res.data.data);
            setMeta(res.data.meta);
        } catch (err) {
            showToast('Failed to load users.', 'red');
        } finally {
            setLoading(false);
        }
    }, []);

    const fetchStats = useCallback(async () => {
        setStatsLoading(true);
        try {
            const res = await api.get('/admin/stats');
            setStats(res.data.data);
        } catch (_) { /* non-critical */ }
        finally { setStatsLoading(false); }
    }, []);

    const fetchPermissions = useCallback(async () => {
        try {
            const res = await api.get('/admin/permissions');
            setAllPermissions(res.data.data);
        } catch (_) { /* non-critical */ }
    }, []);

    useEffect(() => {
        fetchUsers(currentPage);
        fetchStats();
        fetchPermissions();
    }, [currentPage, fetchUsers, fetchStats, fetchPermissions]);

    // ── actions ───────────────────────────────────────────────

    const showToast = (msg) => {
        setToast(msg);
        setTimeout(() => setToast(''), 3500);
    };

    const togglePlan = async (user) => {
        const newPlan = user.plan === 'free' ? 'paid' : 'free';
        try {
            await api.patch(`/admin/users/${user.id}/plan`, { plan: newPlan });
            showToast(`${user.name} moved to ${newPlan} plan.`);
            fetchUsers(currentPage);
            fetchStats(); // Update stats since plan changed
        } catch (err) {
            showToast(err.response?.data?.message || 'Failed to update plan.');
        }
    };

    const savePermissions = async (userId, permissions) => {
        try {
            await api.post(`/admin/users/${userId}/permissions`, { permissions });
            showToast('Permissions saved.');
            setSelectedUser(null);
            fetchUsers(currentPage);
        } catch (err) {
            showToast(err.response?.data?.message || 'Failed to save permissions.');
        }
    };

    // ── render ────────────────────────────────────────────────

    return (
        <div className="space-y-10">

            {/* Stats Section */}
            <section className="space-y-4">
                <h2 className="text-xl font-bold text-gray-800">Marketplace Insights</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {statsLoading ? (
                        [1, 2, 3, 4].map(i => <div key={i} className="h-24 bg-white rounded-xl border animate-pulse" />)
                    ) : stats ? (
                        <>
                            <StatCard title="Total Users" value={stats.users.total} subtext={`${stats.users.paid} paid`} color="blue" />
                            <StatCard title="All Requests" value={stats.requests.total} subtext="life-time" color="purple" />
                            <StatCard title="Pending" value={stats.requests.pending} subtext="to be accepted" color="amber" />
                            <StatCard title="Completed" value={stats.requests.completed} subtext="successfully" color="green" />
                        </>
                    ) : (
                        <p className="text-sm text-gray-400">Stats unavailable</p>
                    )}
                </div>
            </section>

            {/* User Management Section */}
            <section className="space-y-4">
                <div className="flex justify-between items-end">
                    <h2 className="text-xl font-bold text-gray-800">User Management</h2>
                    <p className="text-xs text-gray-400">Manage plans and permission overrides</p>
                </div>

                {/* Toast */}
                {toast && (
                    <div className="fixed top-20 right-8 z-50 shadow-lg p-4 bg-gray-900 text-white rounded-xl text-sm animate-in fade-in slide-in-from-top-4">
                        {toast}
                    </div>
                )}

                {/* User Table */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50/50">
                                <tr>
                                    {['User', 'Email', 'Roles', 'Plan', 'Permissions', 'Actions'].map(h => (
                                        <th key={h} className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-widest">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {loading ? (
                                    <tr><td colSpan={6} className="px-6 py-12 text-center text-gray-400">
                                        <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-gray-300 mx-auto" />
                                    </td></tr>
                                ) : users.length === 0 ? (
                                    <tr><td colSpan={6} className="px-6 py-12 text-center text-gray-400 font-medium">No users found.</td></tr>
                                ) : (
                                    users.map(u => (
                                        <tr key={u.id} className="hover:bg-blue-50/30 transition-colors">
                                            <td className="px-6 py-4 font-bold text-gray-900">{u.name}</td>
                                            <td className="px-6 py-4 text-gray-500 font-medium">{u.email}</td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-wrap gap-1">
                                                    {(u.roles ?? []).map(r => (
                                                        <span key={r} className="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] uppercase font-bold tracking-tighter">
                                                            {r}
                                                        </span>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4"><Badge plan={u.plan} /></td>
                                            <td className="px-6 py-4 text-xs">
                                                <div className="max-w-[150px] truncate text-gray-400 italic">
                                                    {(u.permissions ?? []).length > 0
                                                        ? (u.permissions ?? []).join(', ')
                                                        : 'role defaults'}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex gap-2">
                                                    <button
                                                        onClick={() => togglePlan(u)}
                                                        className={`text-[11px] font-bold px-3 py-1.5 rounded-lg border transition-all ${u.plan === 'free'
                                                            ? 'bg-emerald-50 border-emerald-200 text-emerald-600 hover:bg-emerald-100'
                                                            : 'bg-white border-gray-200 text-gray-500 hover:bg-gray-50'}`}
                                                    >
                                                        {u.plan === 'free' ? 'UPGRADE' : 'DOWNGRADE'}
                                                    </button>
                                                    <button
                                                        onClick={() => setSelectedUser(u)}
                                                        className="text-[11px] font-bold px-3 py-1.5 rounded-lg border border-blue-200 text-blue-600 bg-blue-50/50 hover:bg-blue-100/50 transition-all"
                                                    >
                                                        EDIT PERMS
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between py-2">
                        <p className="text-xs font-bold text-gray-400 uppercase tracking-widest">
                            Showing {users.length} of {meta.total} users
                        </p>
                        <div className="flex gap-2">
                            <button
                                disabled={currentPage <= 1}
                                onClick={() => setCurrentPage(p => p - 1)}
                                className="px-4 py-2 text-xs font-bold border rounded-xl disabled:opacity-20 hover:bg-white transition-all shadow-sm bg-white/50"
                            >
                                PREVIOUS
                            </button>
                            <button
                                disabled={currentPage >= meta.last_page}
                                onClick={() => setCurrentPage(p => p + 1)}
                                className="px-4 py-2 text-xs font-bold border rounded-xl disabled:opacity-20 hover:bg-white transition-all shadow-sm bg-white/50"
                            >
                                NEXT
                            </button>
                        </div>
                    </div>
                )}
            </section>

            {/* Permission Modal */}
            {selectedUser && (
                <PermissionModal
                    user={selectedUser}
                    allPermissions={allPermissions}
                    onClose={() => setSelectedUser(null)}
                    onSave={savePermissions}
                />
            )}
        </div>
    );
}
