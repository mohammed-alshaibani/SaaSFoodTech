'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import { Users, Plus, Edit2, Trash2, RefreshCcw, X, Search, Shield, Mail } from 'lucide-react';

export default function UsersManagement() {
    const { t, isRTL } = useI18n();
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [editingUser, setEditingUser] = useState(null);
    const [formData, setFormData] = useState({ name: '', email: '', password: '', role: '', plan: 'free' });
    const [search, setSearch] = useState('');
    const [message, setMessage] = useState(null);

    const showMessage = useCallback((text, type = 'success') => {
        setMessage({ text, type });
        setTimeout(() => setMessage(null), 3500);
    }, []);

    const fetchUsers = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/admin/users?per_page=100');
            setUsers(res.data.data || []);
        } catch (err) {
            console.error('Failed to load users:', err);
            showMessage(t('permissions.failedToLoad') || 'Failed to load data', 'error');
        } finally {
            setLoading(false);
        }
    }, [showMessage, t]);

    useEffect(() => {
        fetchUsers();
    }, [fetchUsers]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (editingUser) {
                await api.put(`/admin/users/${editingUser.id}`, formData);
                showMessage(t('users.updateSuccess') || 'User updated successfully');
            } else {
                await api.post('/admin/users', formData);
                showMessage(t('users.createSuccess') || 'User created successfully');
            }
            setShowModal(false);
            setEditingUser(null);
            setFormData({ name: '', email: '', password: '', role: '', plan: 'free' });
            fetchUsers();
        } catch (err) {
            console.error('Failed to save user:', err);
            showMessage(t('users.saveFailed') || 'Failed to save user', 'error');
        }
    };

    const handleEdit = (user) => {
        setEditingUser(user);
        setFormData({ 
            name: user.name, 
            email: user.email, 
            password: '', 
            role: user.roles?.[0] || '', 
            plan: user.plan || 'free' 
        });
        setShowModal(true);
    };

    const handleDelete = async (id) => {
        if (!confirm(t('users.confirmDelete') || 'Are you sure you want to delete this user?')) return;
        try {
            await api.delete(`/admin/users/${id}`);
            showMessage(t('users.deleteSuccess') || 'User deleted successfully');
            fetchUsers();
        } catch (err) {
            console.error('Failed to delete user:', err);
            showMessage(t('users.deleteFailed') || 'Failed to delete user', 'error');
        }
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingUser(null);
        setFormData({ name: '', email: '', password: '', role: '', plan: 'free' });
    };

    const filteredUsers = users.filter(u => 
        search === '' || 
        u.name?.toLowerCase().includes(search.toLowerCase()) || 
        u.email?.toLowerCase().includes(search.toLowerCase())
    );

    return (
        <DashboardLayout>
            <div className="space-y-10">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white/50 pb-6 border-b border-gray-100">
                    <div className="flex items-center gap-5">
                        <div className="w-14 h-14 bg-gradient-to-tr from-[#7C3AED] to-purple-400 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-500/20">
                            <Users size={28} fill="currentColor" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-black text-[#1A202C] tracking-tight">{t('users.title') || 'Users Management'}</h1>
                            <p className="text-gray-500 font-medium mt-1">{t('users.subtitle') || 'Create and manage platform users'}</p>
                        </div>
                    </div>
                    <button
                        onClick={() => setShowModal(true)}
                        className="flex items-center justify-center gap-2 px-6 py-3 bg-[#7C3AED] rounded-full text-sm font-bold text-white hover:bg-purple-700 transition-all shadow-md shadow-purple-500/20"
                    >
                        <Plus size={18} /> {t('users.create') || 'Create User'}
                    </button>
                </div>

                {/* Search */}
                <div className={`flex-1 relative w-full ${isRTL ? 'rtl' : ''}`}>
                    <Search className={`absolute top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 ${isRTL ? 'right-4 left-auto' : 'left-4'}`} />
                    <input
                        type="text"
                        placeholder={t('users.searchPlaceholder') || 'Search users...'}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className={`w-full bg-white border border-gray-200 rounded-2xl py-4 text-sm font-bold text-[#1A202C] outline-none hover:border-gray-300 focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition shadow-sm ${isRTL ? 'pr-12 pl-4' : 'pl-12 pr-4'}`}
                    />
                </div>

                {/* Users List */}
                <div className="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left">
                            <thead>
                                <tr className="bg-gray-50/80 border-b border-gray-200">
                                    <th className="px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest min-w-[250px]">{t('users.name') || 'Name'}</th>
                                    <th className="px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest">{t('users.email') || 'Email'}</th>
                                    <th className="px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest">{t('users.role') || 'Role'}</th>
                                    <th className="px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest">{t('users.plan') || 'Plan'}</th>
                                    <th className="px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest text-right">{t('users.actions') || 'Actions'}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loading ? (
                                    <tr><td colSpan={5} className="p-20 text-center"><div className="animate-spin rounded-full h-10 w-10 border-4 border-[#7C3AED] border-t-transparent mx-auto" /></td></tr>
                                ) : filteredUsers.length === 0 ? (
                                    <tr><td colSpan={5} className="p-20 text-center text-gray-400 font-bold">{t('users.noUsers') || 'No users found'}</td></tr>
                                ) : (
                                    filteredUsers.map((user, i) => (
                                        <tr key={user.id} className={`transition-colors ${i % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'} hover:bg-[#7C3AED]/5`}>
                                            <td className="px-8 py-6">
                                                <div className="flex items-center gap-4">
                                                    <div className="w-12 h-12 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-[#1A202C] font-black shadow-sm">
                                                        {user.name?.[0]?.toUpperCase()}
                                                    </div>
                                                    <span className="text-sm font-black text-[#1A202C]">{user.name}</span>
                                                </div>
                                            </td>
                                            <td className="px-8 py-6 text-sm text-gray-500 flex items-center gap-2">
                                                <Mail size={14} /> {user.email}
                                            </td>
                                            <td className="px-8 py-6">
                                                <span className="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-[10px] font-bold uppercase tracking-widest">
                                                    {user.roles?.[0] || '-'}
                                                </span>
                                            </td>
                                            <td className="px-8 py-6">
                                                <span className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest ${user.plan === 'free' ? 'bg-gray-100 text-gray-500' : 'bg-emerald-50 text-emerald-600 border border-emerald-200'}`}>
                                                    {user.plan || 'free'}
                                                </span>
                                            </td>
                                            <td className="px-8 py-6 text-right">
                                                <div className="flex gap-2 justify-end">
                                                    <button onClick={() => handleEdit(user)} className="p-2 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-100 transition">
                                                        <Edit2 size={16} />
                                                    </button>
                                                    <button onClick={() => handleDelete(user.id)} className="p-2 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition">
                                                        <Trash2 size={16} />
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

                {/* Modal */}
                {showModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-3xl p-8 w-full max-w-md shadow-2xl">
                            <div className="flex items-center justify-between mb-6">
                                <h2 className="text-2xl font-black text-[#1A202C]">
                                    {editingUser ? (t('users.edit') || 'Edit User') : (t('users.create') || 'Create User')}
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
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('users.email') || 'Email'}</label>
                                    <input
                                        type="email"
                                        value={formData.email}
                                        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('users.password') || 'Password'} {!editingUser && '*'}</label>
                                    <input
                                        type="password"
                                        value={formData.password}
                                        onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition"
                                        required={!editingUser}
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('users.role') || 'Role'}</label>
                                    <select
                                        value={formData.role}
                                        onChange={(e) => setFormData({ ...formData, role: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition"
                                    >
                                        <option value="">{t('users.selectRole') || 'Select Role'}</option>
                                        <option value="admin">{t('users.admin') || 'Admin'}</option>
                                        <option value="provider_admin">{t('users.providerAdmin') || 'Provider Admin'}</option>
                                        <option value="customer">{t('users.customer') || 'Customer'}</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('users.plan') || 'Plan'}</label>
                                    <select
                                        value={formData.plan}
                                        onChange={(e) => setFormData({ ...formData, plan: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition"
                                    >
                                        <option value="free">{t('users.free') || 'Free'}</option>
                                        <option value="premium">{t('users.premium') || 'Premium'}</option>
                                        <option value="enterprise">{t('users.enterprise') || 'Enterprise'}</option>
                                    </select>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button type="button" onClick={closeModal} className="flex-1 px-6 py-3 bg-gray-100 rounded-full text-sm font-bold text-gray-600 hover:bg-gray-200 transition">
                                        {t('users.cancel') || 'Cancel'}
                                    </button>
                                    <button type="submit" className="flex-1 px-6 py-3 bg-[#7C3AED] rounded-full text-sm font-bold text-white hover:bg-purple-700 transition">
                                        {editingUser ? (t('users.update') || 'Update') : (t('users.create') || 'Create')}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Toast */}
                {message && (
                    <div className={`fixed bottom-10 right-10 z-[60] px-8 py-4 rounded-2xl text-white text-sm font-bold shadow-xl animate-in slide-in-from-bottom-5 ${message.type === 'error' ? 'bg-red-500' : 'bg-[#7C3AED]'}`}>
                        {message.text}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
