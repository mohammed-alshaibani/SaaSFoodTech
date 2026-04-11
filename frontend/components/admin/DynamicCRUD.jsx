'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useI18n } from '@/context/I18nContext';
import { Plus, Edit2, Trash2, X, Search, RefreshCcw, Save, AlertCircle } from 'lucide-react';

/**
 * DynamicCRUD Component
 * Handles Generic CRUD operations based on a configuration object.
 * 
 * @param {Object} config - Configuration for the CRUD module
 * @param {string} config.title - Module title
 * @param {string} config.endpoint - Base API endpoint
 * @param {Array} config.fields - Field definitions
 * @param {Function} config.onDataLoad - Optional callback after data is loaded
 * @param {Function} config.onAfterSave - Optional callback after successful save (create/update)
 */
export default function DynamicCRUD({ config }) {
    const { t, isRTL } = useI18n();
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [formData, setFormData] = useState({});
    const [search, setSearch] = useState('');
    const [message, setMessage] = useState(null);

    const { title, endpoint, fields, icon: Icon, onAfterSave } = config;

    const showMessage = useCallback((text, type = 'success') => {
        setMessage({ text, type });
        setTimeout(() => setMessage(null), 3500);
    }, []);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            console.log(`Fetching data from ${endpoint}?per_page=100`);
            const res = await api.get(`${endpoint}?per_page=100`);
            const loadedData = res.data.data || res.data || [];
            console.log(`Loaded ${loadedData.length} items from ${endpoint}`);
            setData(Array.isArray(loadedData) ? loadedData : []);
            if (config.onDataLoad) config.onDataLoad(loadedData);
        } catch (err) {
            console.error(`Failed to load ${title}:`, err);
            showMessage(t('common.errorLoading') || 'Failed to load data', 'error');
        } finally {
            setLoading(false);
        }
    }, [endpoint, title, showMessage, t, config]);

    useEffect(() => {
        fetchData();
        // Initialize formData with default values
        const defaults = {};
        fields.forEach(f => {
            if (f.form) defaults[f.name] = f.defaultValue !== undefined ? f.defaultValue : '';
        });
        setFormData(defaults);
    }, [fetchData, fields]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            console.log(`Submitting form for ${title}:`, { editingItem: editingItem?.id, formData });
            
            // Remove guard_name from formData since it's handled by backend
            const submitData = { ...formData };
            delete submitData.guard_name;
            
            if (editingItem) {
                const response = await api.put(`${endpoint}/${editingItem.id}`, submitData);
                console.log('Update response:', response);
                showMessage(t('common.updateSuccess') || 'Updated successfully');
            } else {
                const response = await api.post(endpoint, submitData);
                console.log('Create response:', response);
                showMessage(t('common.createSuccess') || 'Created successfully');
            }
            // Close modal immediately before any async operations
            closeModal();
            // Trigger callback after successful save (e.g., to refresh user permissions)
            if (onAfterSave) onAfterSave();
            // Refresh data from server to get the latest state
            await fetchData();
        } catch (err) {
            console.error(`Failed to save ${title}:`, err);
            console.error('Error response:', err.response?.data);
            const errorMsg = err.response?.data?.message || err.response?.data?.error || t('common.saveFailed') || 'Failed to save';
            
            // Show validation errors if present
            if (err.response?.data?.errors) {
                const errorDetails = Object.entries(err.response.data.errors)
                    .map(([field, messages]) => `${field}: ${Array.isArray(messages) ? messages.join(', ') : messages}`)
                    .join('\n');
                showMessage(`${errorMsg}\n${errorDetails}`, 'error');
            } else {
                showMessage(errorMsg, 'error');
            }
        }
    };

    const handleEdit = (item) => {
        setEditingItem(item);
        const mappedData = {};
        fields.forEach(f => {
            if (f.form) {
                // Handle nested keys like 'roles.0' if needed, but keeping it simple for now
                mappedData[f.name] = item[f.name] || '';

                // Special mapping for roles
                if (f.name === 'role' && item.roles) {
                    mappedData[f.name] = item.roles[0]?.name || item.roles[0] || '';
                }

                // Special mapping for permissions (role permissions)
                if (f.name === 'permissions' && item.permissions) {
                    mappedData[f.name] = item.permissions.map(p => p.id);
                }
            }
        });
        setFormData(mappedData);
        setShowModal(true);
    };

    const handleDelete = async (id) => {
        if (!confirm(t('common.confirmDelete') || 'هل أنت متأكد؟')) return;
        try {
            console.log(`Deleting ${endpoint}/${id}`);
            const response = await api.delete(`${endpoint}/${id}`);
            console.log('Delete response:', response);
            showMessage(t('common.deleteSuccess') || 'تم الحذف بنجاح');
            // Update local state immediately to reflect deletion
            setData(prev => prev.filter(item => item.id !== id));
            // Refresh data from server to confirm deletion
            await fetchData();
        } catch (err) {
            console.error(`Failed to delete ${title}:`, err);
            console.error('Error response data:', err.response?.data);
            let errorMsg = t('common.deleteFailed') || 'فشل الحذف';
            
            // Display detailed error message from API
            if (err.response?.data?.message) {
                errorMsg = err.response.data.message;
            } else if (err.response?.data?.error) {
                errorMsg = err.response.data.error;
            }
            
            showMessage(errorMsg, 'error');
        }
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingItem(null);
        const defaults = {};
        fields.forEach(f => {
            if (f.form) defaults[f.name] = f.defaultValue !== undefined ? f.defaultValue : '';
        });
        setFormData(defaults);
    };

    const filteredData = data.filter(item => {
        if (!search) return true;
        return fields.some(f => {
            if (!f.table) return false;
            const val = item[f.name];
            return val && String(val).toLowerCase().includes(search.toLowerCase());
        });
    });

    return (
        <div className="space-y-10 animate-in fade-in duration-500">
            {/* Header */}
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white/50 pb-6 border-b border-gray-100">
                <div className="flex items-center gap-5">
                    <div className="w-14 h-14 bg-gradient-to-tr from-[#7C3AED] to-purple-400 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-500/20">
                        {Icon ? <Icon size={28} fill="currentColor" /> : <AlertCircle size={28} />}
                    </div>
                    <div>
                        <h1 className="text-3xl font-black text-[#1A202C] tracking-tight">{title}</h1>
                        <p className="text-gray-500 font-medium mt-1">{t('common.manage') || 'Manage'} {title.toLowerCase()}</p>
                    </div>
                </div>
                <div className="flex gap-3">
                    <button
                        onClick={fetchData}
                        className="p-3 bg-white border border-gray-200 rounded-2xl text-gray-500 hover:bg-gray-50 transition shadow-sm"
                        title={t('common.refresh') || 'Refresh'}
                    >
                        <RefreshCcw size={20} className={loading ? 'animate-spin' : ''} />
                    </button>
                    <button
                        onClick={() => setShowModal(true)}
                        className="px-6 py-3 bg-[#7C3AED] text-white rounded-xl font-black hover:bg-[#6D28D9] transition-all shadow-lg shadow-[#7C3AED]/30 flex items-center gap-2"
                    >
                        <Plus size={18} />
                        {t('common.create') || 'إنشاء جديد'}
                    </button>
                </div>
            </div>

            {/* Search */}
            <div className={`relative w-full ${isRTL ? 'rtl' : ''}`}>
                <Search className={`absolute top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 ${isRTL ? 'right-4 left-auto' : 'left-4'}`} />
                <input
                    type="text"
                    placeholder={`${t('common.search') || 'Search'} ${title.toLowerCase()}...`}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className={`w-full bg-white border border-gray-200 rounded-2xl py-4 text-sm font-bold text-[#1A202C] outline-none hover:border-gray-300 focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition shadow-sm ${isRTL ? 'pr-12 pl-4' : 'pl-12 pr-4'}`}
                />
            </div>

            {/* Table */}
            <div className="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm text-left">
                        <thead>
                            <tr className="bg-gray-50/80 border-b border-gray-200">
                                {fields.filter(f => f.table).map(f => (
                                    <th key={f.name} className={`px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest ${isRTL ? 'text-right' : 'text-left'}`}>
                                        {f.label}
                                    </th>
                                ))}
                                <th className={`px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest ${isRTL ? 'text-left' : 'text-right'}`}>
                                    {t('common.actions') || 'Actions'}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {loading && data.length === 0 ? (
                                <tr><td colSpan={fields.length + 1} className="p-20 text-center"><div className="animate-spin rounded-full h-10 w-10 border-4 border-[#7C3AED] border-t-transparent mx-auto" /></td></tr>
                            ) : filteredData.length === 0 ? (
                                <tr><td colSpan={fields.length + 1} className="p-20 text-center text-gray-400 font-bold">{t('common.noData') || 'No data found'}</td></tr>
                            ) : (
                                filteredData.map((item, i) => (
                                    <tr key={item.id} className={`transition-colors ${i % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'} hover:bg-[#7C3AED]/5`}>
                                        {fields.filter(f => f.table).map(f => (
                                            <td key={f.name} className={`px-8 py-6 ${isRTL ? 'text-right' : 'text-left'}`}>
                                                {f.render ? f.render(item[f.name], item) : (
                                                    <span className="text-sm font-bold text-[#1A202C]">{item[f.name] || '-'}</span>
                                                )}
                                            </td>
                                        ))}
                                        <td className="px-8 py-6 text-right">
                                            <div className={`flex gap-2 ${isRTL ? 'justify-start' : 'justify-end'}`}>
                                                <button onClick={() => handleEdit(item)} className="p-2 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-100 transition">
                                                    <Edit2 size={16} />
                                                </button>
                                                <button onClick={() => handleDelete(item.id)} className="p-2 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition">
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
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
                    <div className="bg-white rounded-3xl p-8 w-full max-w-lg shadow-2xl animate-in zoom-in-95 duration-200">
                        <div className="flex items-center justify-between mb-6">
                            <h2 className="text-2xl font-black text-[#1A202C]">
                                {editingItem ? `${t('common.edit') || 'Edit'} ${title}` : `${t('common.create') || 'Create'} ${title}`}
                            </h2>
                            <button onClick={closeModal} className="p-2 hover:bg-gray-100 rounded-xl transition">
                                <X size={20} />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 max-h-[60vh] overflow-y-auto px-1 custom-scrollbar">
                                {fields.filter(f => f.form).map(f => (
                                    <div key={f.name}>
                                        <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{f.label}</label>
                                        
                                        {f.renderForm ? f.renderForm(formData[f.name], (val) => setFormData({ ...formData, [f.name]: val }), formData) : f.type === 'select' ? (
                                            <select
                                                value={formData[f.name]}
                                                onChange={(e) => setFormData({ ...formData, [f.name]: e.target.value })}
                                                className="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-4 text-sm font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition appearance-none"
                                                required={f.required}
                                            >
                                                <option value="">{t('common.select') || 'Select'}...</option>
                                                {f.options?.map(opt => (
                                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                ))}
                                            </select>
                                        ) : f.type === 'textarea' ? (
                                            <textarea
                                                value={formData[f.name]}
                                                onChange={(e) => setFormData({ ...formData, [f.name]: e.target.value })}
                                                className="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-4 text-sm font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition resize-none"
                                                rows={3}
                                                required={f.required}
                                            />
                                        ) : (
                                            <input
                                                type={f.type || 'text'}
                                                value={formData[f.name]}
                                                onChange={(e) => setFormData({ ...formData, [f.name]: e.target.value })}
                                                className="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-4 text-sm font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition"
                                                placeholder={f.label}
                                                required={f.required}
                                            />
                                        )}
                                    </div>
                                ))}
                            </div>
                            
                            <div className="flex gap-3 pt-6 border-t border-gray-100">
                                <button type="button" onClick={closeModal} className="flex-1 px-6 py-4 bg-gray-100 rounded-2xl text-sm font-bold text-gray-500 hover:bg-gray-200 transition">
                                    {t('common.cancel') || 'Cancel'}
                                </button>
                                <button type="submit" className="flex-1 px-6 py-4 bg-[#7C3AED] rounded-2xl text-sm font-bold text-white hover:bg-purple-700 transition flex items-center justify-center gap-2">
                                    <Save size={18} /> {editingItem ? (t('common.update') || 'Update') : (t('common.create') || 'Create')}
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
    );
}
