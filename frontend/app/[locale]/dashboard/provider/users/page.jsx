'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import {
    Users,
    Search,
    Filter,
    MoreVertical,
    CheckCircle2,
    AlertCircle,
    Mail,
    Phone,
    Building2,
    Crown,
    Star,
    Calendar,
    ArrowUpRight,
    Shield,
    UserPlus,
    Ban,
    Edit3,
    X,
    Plus
} from 'lucide-react';

export default function ProviderUsersPage() {
    const { user } = useAuth();
    const { t, language } = useI18n();
    const isRTL = language === 'ar';

    const [users, setUsers] = useState([]);
    const [filteredUsers, setFilteredUsers] = useState([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [filterStatus, setFilterStatus] = useState('all');
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState(null);
    const [showModal, setShowModal] = useState(false);
    const [editingUser, setEditingUser] = useState(null);
    const [formData, setFormData] = useState({ name: '', email: '', role: 'provider_employee', status: 'active', permissions: [] });

    const fetchUsers = useCallback(async () => {
        try {
            const res = await api.get('/provider/users');
            const userData = res.data.data || [];
            setUsers(userData);
            setFilteredUsers(userData);
        } catch (err) {
            console.error('Failed to fetch users:', err);
            setMessage({ type: 'error', text: t('users.fetchError') || 'Failed to load users' });
        } finally {
            setLoading(false);
        }
    }, [t]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (editingUser) {
                await api.put(`/provider/users/${editingUser.id}`, formData);
                setMessage({ type: 'success', text: t('users.updateSuccess') || 'User updated successfully' });
            } else {
                await api.post('/provider/users', formData);
                setMessage({ type: 'success', text: t('users.createSuccess') || 'User created successfully' });
            }
            setShowModal(false);
            setEditingUser(null);
            setFormData({ name: '', email: '', role: 'provider_employee', status: 'active', permissions: [] });
            fetchUsers();
        } catch (err) {
            console.error('Failed to save user:', err);
            setMessage({ type: 'error', text: t('users.saveFailed') || 'Failed to save user' });
        }
    };

    const handleEdit = (user) => {
        setEditingUser(user);
        setFormData({
            name: user.name,
            email: user.email,
            role: user.parsed_role || 'provider_employee',
            status: user.status || 'active',
            permissions: user.direct_permissions || []
        });
        setShowModal(true);
    };

    const handleDelete = async (id) => {
        if (!confirm(t('users.confirmDelete') || 'Are you sure you want to delete this user?')) return;
        try {
            await api.delete(`/provider/users/${id}`);
            setMessage({ type: 'success', text: t('users.deleteSuccess') || 'User deleted successfully' });
            fetchUsers();
        } catch (err) {
            console.error('Failed to delete user:', err);
            setMessage({ type: 'error', text: t('users.deleteFailed') || 'Failed to delete user' });
        }
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingUser(null);
        setFormData({ name: '', email: '', role: 'provider_employee', status: 'active', permissions: [] });
    };

    useEffect(() => {
        fetchUsers();
    }, [fetchUsers]);

    useEffect(() => {
        let result = users;

        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            result = result.filter(u =>
                u.name?.toLowerCase().includes(query) ||
                u.email?.toLowerCase().includes(query) ||
                u.company?.toLowerCase().includes(query)
            );
        }

        if (filterStatus !== 'all') {
            result = result.filter(u => u.status === filterStatus);
        }

        setFilteredUsers(result);
    }, [searchQuery, filterStatus, users]);

    const StatCard = ({ icon: Icon, title, value, color }) => {
        const colors = {
            blue: 'bg-blue-50 border-blue-200 text-blue-600',
            emerald: 'bg-emerald-50 border-emerald-200 text-emerald-600',
            amber: 'bg-amber-50 border-amber-200 text-amber-600',
            purple: 'bg-purple-50 border-purple-200 text-purple-600',
        };

        return (
            <div className={`p-6 rounded-2xl border ${colors[color]} backdrop-blur-sm`}>
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium opacity-80">{title}</p>
                        <p className="text-2xl font-black mt-1">{value}</p>
                    </div>
                    <div className="p-3 bg-white/50 rounded-xl">
                        <Icon size={24} />
                    </div>
                </div>
            </div>
        );
    };

    const StatusBadge = ({ status }) => {
        const config = {
            active: { color: 'bg-emerald-100 text-emerald-700 border-emerald-200', label: t('users.active') || 'Active' },
            inactive: { color: 'bg-gray-100 text-gray-700 border-gray-200', label: t('users.inactive') || 'Inactive' },
            suspended: { color: 'bg-red-100 text-red-700 border-red-200', label: t('users.suspended') || 'Suspended' },
            pending: { color: 'bg-amber-100 text-amber-700 border-amber-200', label: t('users.pending') || 'Pending' },
        };
        const configItem = config[status] || config.active;

        return (
            <span className={`px-3 py-1 rounded-full text-xs font-bold border ${configItem.color}`}>
                {configItem.label}
            </span>
        );
    };

    const RoleBadge = ({ role }) => {
        const colors = {
            admin: 'bg-purple-100 text-purple-700 border-purple-200',
            provider: 'bg-blue-100 text-blue-700 border-blue-200',
            customer: 'bg-emerald-100 text-emerald-700 border-emerald-200',
        };

        return (
            <span className={`px-3 py-1 rounded-full text-xs font-bold border ${colors[role] || colors.customer}`}>
                {role || 'customer'}
            </span>
        );
    };

    if (loading) {
        return (
            <DashboardLayout>
                <div className="flex items-center justify-center h-96">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </DashboardLayout>
        );
    }

    return (
        <DashboardLayout>
            <div className={`space-y-8 ${isRTL ? 'text-right' : 'text-left'}`}>
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black text-gray-900">
                            {t('users.title') || 'Users Management'}
                        </h1>
                        <p className="text-gray-500 mt-1">
                            {t('users.subtitle') || 'Manage your customers and team members'}
                        </p>
                    </div>
                    <button onClick={() => setShowModal(true)} className="flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-colors shadow-lg shadow-blue-200">
                        <UserPlus size={20} />
                        {t('users.addUser') || 'Add User'}
                    </button>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        icon={Users}
                        title={t('users.total') || 'Total Users'}
                        value={users.length}
                        color="blue"
                    />
                    <StatCard
                        icon={CheckCircle2}
                        title={t('users.active') || 'Active'}
                        value={users.filter(u => u.status === 'active').length}
                        color="emerald"
                    />
                    <StatCard
                        icon={Crown}
                        title={t('users.premium') || 'Premium'}
                        value={users.filter(u => u.plan === 'premium' || u.plan === 'enterprise').length}
                        color="amber"
                    />
                    <StatCard
                        icon={Shield}
                        title={t('users.newThisMonth') || 'New This Month'}
                        value={users.filter(u => {
                            const created = new Date(u.created_at);
                            const now = new Date();
                            return created.getMonth() === now.getMonth() && created.getFullYear() === now.getFullYear();
                        }).length}
                        color="purple"
                    />
                </div>

                {/* Message */}
                {message && (
                    <div className={`p-4 rounded-xl border ${message.type === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'}`}>
                        <div className="flex items-center gap-2">
                            {message.type === 'error' ? <AlertCircle size={20} /> : <CheckCircle2 size={20} />}
                            <span className="font-bold">{message.text}</span>
                        </div>
                    </div>
                )}

                {/* Filters */}
                <div className="flex flex-col md:flex-row gap-4">
                    <div className="flex-1 relative">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
                        <input
                            type="text"
                            placeholder={t('users.search') || 'Search users...'}
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full pl-12 pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>
                    <select
                        value={filterStatus}
                        onChange={(e) => setFilterStatus(e.target.value)}
                        className="px-4 py-3 bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="all">{t('users.allStatuses') || 'All Statuses'}</option>
                        <option value="active">{t('users.active') || 'Active'}</option>
                        <option value="inactive">{t('users.inactive') || 'Inactive'}</option>
                        <option value="suspended">{t('users.suspended') || 'Suspended'}</option>
                    </select>
                </div>

                {/* Users Table */}
                <div className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    {filteredUsers.length === 0 ? (
                        <div className="p-12 text-center">
                            <div className="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <Users size={32} className="text-gray-400" />
                            </div>
                            <h3 className="text-lg font-bold text-gray-900 mb-2">
                                {t('users.noUsers') || 'No users found'}
                            </h3>
                            <p className="text-gray-500">
                                {searchQuery ? t('users.noSearchResults') || 'Try adjusting your search' : t('users.noUsersDesc') || 'Users will appear here'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('users.user') || 'User'}
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('users.role') || 'Role'}
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('users.status') || 'Status'}
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('users.plan') || 'Plan'}
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('users.joined') || 'Joined'}
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('users.actions') || 'Actions'}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {filteredUsers.map((u) => (
                                        <tr key={u.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center text-white font-bold">
                                                        {u.name?.charAt(0) || 'U'}
                                                    </div>
                                                    <div>
                                                        <p className="font-bold text-gray-900">{u.name || 'Unknown'}</p>
                                                        <p className="text-sm text-gray-500">{u.email}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <RoleBadge role={u.parsed_role || 'provider_employee'} />
                                            </td>
                                            <td className="px-6 py-4">
                                                <StatusBadge status={u.status || 'active'} />
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="inline-flex items-center px-3 py-1 rounded-lg text-sm font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                    {u.plan || 'Free'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-600">
                                                {u.created_at ? new Date(u.created_at).toLocaleDateString() : '-'}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-2">
                                                    <button onClick={() => handleEdit(u)} className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                                        <Edit3 size={18} />
                                                    </button>
                                                    <button onClick={() => handleDelete(u.id)} className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                                        <Ban size={18} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Modal */}
                {showModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-3xl p-8 w-full max-w-md shadow-2xl">
                            <div className="flex items-center justify-between mb-6">
                                <h2 className="text-2xl font-black text-gray-900">
                                    {editingUser ? (t('users.edit') || 'Edit User') : (t('users.addUser') || 'Add User')}
                                </h2>
                                <button onClick={closeModal} className="p-2 hover:bg-gray-100 rounded-xl transition">
                                    <X size={20} />
                                </button>
                            </div>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('users.name') || 'Name'}</label>
                                    <input
                                        type="text"
                                        value={formData.name}
                                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('users.email') || 'Email'}</label>
                                    <input
                                        type="email"
                                        value={formData.email}
                                        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('users.role') || 'Role'}</label>
                                    <select
                                        value={formData.role}
                                        onChange={(e) => setFormData({ ...formData, role: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                                    >
                                        <option value="provider_employee">{t('users.providerEmployee') || 'Provider Employee'}</option>
                                        <option value="provider_admin">{t('users.providerAdmin') || 'Provider Admin'}</option>
                                    </select>
                                </div>
                                {formData.role === 'provider_employee' && (
                                    <div>
                                        <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Granular Permissions</label>
                                        <div className="grid grid-cols-2 gap-3 mt-1 bg-gray-50 p-4 border border-gray-100 rounded-xl">
                                            {[
                                                { id: 'request.view_all', label: 'View All Requests' },
                                                { id: 'request.accept', label: 'Accept Requests' },
                                                { id: 'request.complete', label: 'Complete Requests' },
                                                { id: 'request.view_nearby', label: 'Discover Nearby' },
                                            ].map(perm => (
                                                <label key={perm.id} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={formData.permissions.includes(perm.id)}
                                                        onChange={(e) => {
                                                            if (e.target.checked) {
                                                                setFormData({ ...formData, permissions: [...formData.permissions, perm.id] });
                                                            } else {
                                                                setFormData({ ...formData, permissions: formData.permissions.filter(p => p !== perm.id) });
                                                            }
                                                        }}
                                                        className="rounded text-blue-600 focus:ring-blue-500 h-4 w-4"
                                                    />
                                                    <span className="font-medium">{perm.label}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('users.status') || 'Status'}</label>
                                    <select
                                        value={formData.status}
                                        onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                                    >
                                        <option value="active">{t('users.active') || 'Active'}</option>
                                        <option value="inactive">{t('users.inactive') || 'Inactive'}</option>
                                        <option value="suspended">{t('users.suspended') || 'Suspended'}</option>
                                    </select>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button type="button" onClick={closeModal} className="flex-1 px-6 py-3 bg-gray-100 rounded-full text-sm font-bold text-gray-600 hover:bg-gray-200 transition">
                                        {t('users.cancel') || 'Cancel'}
                                    </button>
                                    <button type="submit" className="flex-1 px-6 py-3 bg-blue-600 rounded-full text-sm font-bold text-white hover:bg-blue-700 transition">
                                        {editingUser ? (t('users.update') || 'Update') : (t('users.create') || 'Create')}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
