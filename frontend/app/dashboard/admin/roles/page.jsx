'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import { Shield, Plus, Edit2, Trash2, RefreshCcw, X, Check } from 'lucide-react';

export default function RolesManagement() {
    const { t } = useI18n();
    const [roles, setRoles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
    const [formData, setFormData] = useState({ name: '', display_name: '', description: '' });
    const [message, setMessage] = useState(null);

    const showMessage = useCallback((text, type = 'success') => {
        setMessage({ text, type });
        setTimeout(() => setMessage(null), 3500);
    }, []);

    const fetchRoles = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/admin/roles');
            setRoles(res.data.data || []);
        } catch (err) {
            console.error('Failed to load roles:', err);
            showMessage(t('permissions.failedToLoad') || 'Failed to load data', 'error');
        } finally {
            setLoading(false);
        }
    }, [showMessage, t]);

    useEffect(() => {
        fetchRoles();
    }, [fetchRoles]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (editingRole) {
                await api.put(`/admin/roles/${editingRole.id}`, formData);
                showMessage(t('permissions.updateSuccess') || 'Role updated successfully');
            } else {
                await api.post('/admin/roles', formData);
                showMessage(t('permissions.createSuccess') || 'Role created successfully');
            }
            setShowModal(false);
            setEditingRole(null);
            setFormData({ name: '', display_name: '', description: '' });
            fetchRoles();
        } catch (err) {
            console.error('Failed to save role:', err);
            showMessage(t('permissions.saveFailed') || 'Failed to save role', 'error');
        }
    };

    const handleEdit = (role) => {
        setEditingRole(role);
        setFormData({ name: role.name, display_name: role.display_name || '', description: role.description || '' });
        setShowModal(true);
    };

    const handleDelete = async (id) => {
        if (!confirm(t('permissions.confirmDelete') || 'Are you sure you want to delete this role?')) return;
        try {
            await api.delete(`/admin/roles/${id}`);
            showMessage(t('permissions.deleteSuccess') || 'Role deleted successfully');
            fetchRoles();
        } catch (err) {
            console.error('Failed to delete role:', err);
            showMessage(t('permissions.deleteFailed') || 'Failed to delete role', 'error');
        }
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingRole(null);
        setFormData({ name: '', display_name: '', description: '' });
    };

    return (
        <DashboardLayout>
            <div className="space-y-10">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white/50 pb-6 border-b border-gray-100">
                    <div className="flex items-center gap-5">
                        <div className="w-14 h-14 bg-gradient-to-tr from-[#7C3AED] to-purple-400 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-500/20">
                            <Shield size={28} fill="currentColor" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-black text-[#1A202C] tracking-tight">{t('roles.title') || 'Roles Management'}</h1>
                            <p className="text-gray-500 font-medium mt-1">{t('roles.subtitle') || 'Create and manage user roles'}</p>
                        </div>
                    </div>
                    <button
                        onClick={() => setShowModal(true)}
                        className="flex items-center justify-center gap-2 px-6 py-3 bg-[#7C3AED] rounded-full text-sm font-bold text-white hover:bg-purple-700 transition-all shadow-md shadow-purple-500/20"
                    >
                        <Plus size={18} /> {t('roles.create') || 'Create Role'}
                    </button>
                </div>

                {/* Roles List */}
                <div className="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left">
                            <thead>
                                <tr className="bg-gray-50/80 border-b border-gray-200">
                                    <th className="px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest">Name</th>
                                    <th className="px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest">Display Name</th>
                                    <th className="px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest">Description</th>
                                    <th className="px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loading ? (
                                    <tr><td colSpan={4} className="p-20 text-center"><div className="animate-spin rounded-full h-10 w-10 border-4 border-[#7C3AED] border-t-transparent mx-auto" /></td></tr>
                                ) : roles.length === 0 ? (
                                    <tr><td colSpan={4} className="p-20 text-center text-gray-400 font-bold">{t('roles.noRoles') || 'No roles found'}</td></tr>
                                ) : (
                                    roles.map((role, i) => (
                                        <tr key={role.id} className={`transition-colors ${i % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'} hover:bg-[#7C3AED]/5`}>
                                            <td className="px-8 py-6">
                                                <span className="px-3 py-1 bg-[#7C3AED]/10 text-[#7C3AED] rounded-full text-[10px] font-bold uppercase tracking-widest">{role.name}</span>
                                            </td>
                                            <td className="px-8 py-6 text-sm font-bold text-[#1A202C]">{role.display_name || '-'}</td>
                                            <td className="px-8 py-6 text-sm text-gray-500">{role.description || '-'}</td>
                                            <td className="px-8 py-6 text-right">
                                                <div className="flex gap-2 justify-end">
                                                    <button onClick={() => handleEdit(role)} className="p-2 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-100 transition">
                                                        <Edit2 size={16} />
                                                    </button>
                                                    <button onClick={() => handleDelete(role.id)} className="p-2 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition">
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
                                    {editingRole ? (t('roles.edit') || 'Edit Role') : (t('roles.create') || 'Create Role')}
                                </h2>
                                <button onClick={closeModal} className="p-2 hover:bg-gray-100 rounded-xl transition">
                                    <X size={20} />
                                </button>
                            </div>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('roles.name') || 'Role Name'}</label>
                                    <input
                                        type="text"
                                        value={formData.name}
                                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('roles.displayName') || 'Display Name'}</label>
                                    <input
                                        type="text"
                                        value={formData.display_name}
                                        onChange={(e) => setFormData({ ...formData, display_name: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition"
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('roles.description') || 'Description'}</label>
                                    <textarea
                                        value={formData.description}
                                        onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition resize-none"
                                        rows={3}
                                    />
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button type="button" onClick={closeModal} className="flex-1 px-6 py-3 bg-gray-100 rounded-full text-sm font-bold text-gray-600 hover:bg-gray-200 transition">
                                        {t('roles.cancel') || 'Cancel'}
                                    </button>
                                    <button type="submit" className="flex-1 px-6 py-3 bg-[#7C3AED] rounded-full text-sm font-bold text-white hover:bg-purple-700 transition">
                                        {editingRole ? (t('roles.update') || 'Update') : (t('roles.create') || 'Create')}
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
