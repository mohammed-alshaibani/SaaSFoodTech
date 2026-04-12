'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import {
    FileText, RefreshCcw, Clock, CheckCircle2, XCircle,
    Search, Filter, User, MapPin, Calendar, Eye, Loader2, Trash2, Edit3, ChevronDown
} from 'lucide-react';

const STATUS_COLORS = {
    pending: 'bg-amber-100 text-amber-700 border-amber-200',
    accepted: 'bg-blue-100 text-blue-700 border-blue-200',
    work_done: 'bg-purple-100 text-purple-700 border-purple-200',
    completed: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    cancelled: 'bg-red-100 text-red-700 border-red-200',
};

const STATUS_LABELS = {
    pending: 'في الانتظار',
    accepted: 'مقبول',
    work_done: 'منجز',
    completed: 'مكتمل',
    cancelled: 'ملغي',
};

export default function AdminRequestsDashboard() {
    const { t } = useI18n();

    const [requests, setRequests] = useState([]);
    const [filtered, setFiltered] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [message, setMessage] = useState(null);
    const [actionLoading, setActionLoading] = useState(null);
    const [selectedRequest, setSelectedRequest] = useState(null);
    const [isEditing, setIsEditing] = useState(false);
    const [editData, setEditData] = useState({});

    const showMessage = useCallback((text, type = 'success') => {
        setMessage({ text, type });
        setTimeout(() => setMessage(null), 3000);
    }, []);

    const startEditing = (req) => {
        setSelectedRequest(req);
        setEditData({
            title: req.title,
            description: req.description,
            status: req.status,
            latitude: req.latitude,
            longitude: req.longitude,
        });
        setIsEditing(true);
    };

    const fetchRequests = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/requests');
            const data = res.data?.data || res.data || [];
            setRequests(data);
            setFiltered(data);
        } catch (err) {
            console.error('Failed to fetch requests:', err);
            showMessage('فشل تحميل الطلبات', 'error');
        } finally {
            setLoading(false);
        }
    }, [showMessage]);

    const handleSaveUpdate = async () => {
        if (!selectedRequest) return;
        setActionLoading(selectedRequest.id);
        try {
            await api.put(`/requests/${selectedRequest.id}`, editData);
            showMessage('تم تحديث بيانات الطلب بنجاح');
            setSelectedRequest(null);
            fetchRequests();
        } catch (err) {
            console.error('Update failed:', err);
            showMessage('فشل تحديث البيانات', 'error');
        } finally {
            setActionLoading(null);
        }
    };

    const handleQuickStatusUpdate = async (requestId, newStatus) => {
        setActionLoading(requestId);
        try {
            await api.put(`/requests/${requestId}`, { status: newStatus });
            showMessage('تم تحديث الحالة بنجاح');
            fetchRequests();
        } catch (err) {
            console.error('Update failed:', err);
            showMessage('فشل تحديث الحالة', 'error');
        } finally {
            setActionLoading(null);
        }
    };

    const handleDeleteRequest = async (requestId) => {
        if (!confirm('هل أنت متأكد من حذف هذا الطلب نهائياً؟')) return;

        setActionLoading(requestId);
        try {
            const res = await api.delete(`/requests/${requestId}`);
            if (res.data.success || res.status === 200 || res.status === 204) {
                // Update local state immediately for speed
                setRequests(prev => prev.filter(r => r.id !== requestId));
                setFiltered(prev => prev.filter(r => r.id !== requestId));

                showMessage('تم حذف الطلب بنجاح');
                setSelectedRequest(null);
                // Also trigger a fresh fetch to be sure
                setTimeout(() => fetchRequests(), 500);
            } else {
                throw new Error(res.data.message || 'Deletion failed');
            }
        } catch (err) {
            console.error('Delete failed:', err);
            const errorMsg = err.response?.data?.message || err.message || 'فشل حذف الطلب';
            showMessage(errorMsg, 'error');
        } finally {
            setActionLoading(null);
        }
    };

    useEffect(() => { fetchRequests(); }, [fetchRequests]);

    useEffect(() => {
        let result = requests;
        if (search) {
            const q = search.toLowerCase();
            result = result.filter(r =>
                r.title?.toLowerCase().includes(q) ||
                r.description?.toLowerCase().includes(q) ||
                r.customer?.name?.toLowerCase().includes(q)
            );
        }
        if (statusFilter !== 'all') {
            result = result.filter(r => r.status === statusFilter);
        }
        setFiltered(result);
    }, [search, statusFilter, requests]);

    const stats = {
        total: requests.length,
        pending: requests.filter(r => r.status === 'pending').length,
        completed: requests.filter(r => r.status === 'completed').length,
        cancelled: requests.filter(r => r.status === 'cancelled').length,
    };

    return (
        <DashboardLayout>
            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-gray-100">
                    <div className="flex items-center gap-5">
                        <div className="w-14 h-14 bg-gradient-to-tr from-[#7C3AED] to-purple-400 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-500/20">
                            <FileText size={28} fill="currentColor" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-black text-[#1A202C] tracking-tight">{t('requests.title')}</h1>
                            <p className="text-gray-500 font-medium mt-1">{t('requests.subtitle')}</p>
                        </div>
                    </div>
                    <button
                        onClick={fetchRequests}
                        disabled={loading}
                        className="flex items-center justify-center gap-2 px-6 py-3 bg-white border-2 border-[#7C3AED] rounded-full text-sm font-bold text-[#7C3AED] hover:bg-[#7C3AED] hover:text-white transition-all shadow-sm disabled:opacity-50"
                    >
                        <RefreshCcw size={18} className={loading ? 'animate-spin' : ''} />
                        {t('requests.refresh')}
                    </button>
                </div>

                {/* Message */}
                {message && (
                    <div className={`p-4 rounded-2xl text-sm font-bold ${message.type === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}`}>
                        {message.text}
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    {[
                        { label: 'الإجمالي', value: stats.total, icon: FileText, color: 'bg-purple-50 text-purple-600' },
                        { label: t('requests.pending'), value: stats.pending, icon: Clock, color: 'bg-amber-50 text-amber-600' },
                        { label: t('requests.completed'), value: stats.completed, icon: CheckCircle2, color: 'bg-emerald-50 text-emerald-600' },
                        { label: t('requests.cancelled'), value: stats.cancelled, icon: XCircle, color: 'bg-red-50 text-red-600' },
                    ].map(({ label, value, icon: Icon, color }) => (
                        <div key={label} className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
                            <div className={`w-12 h-12 rounded-xl flex items-center justify-center ${color}`}>
                                <Icon size={22} />
                            </div>
                            <div>
                                <p className="text-2xl font-black text-[#1A202C]">{value}</p>
                                <p className="text-xs font-medium text-gray-500">{label}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Filters */}
                <div className="flex flex-col sm:flex-row gap-4">
                    <div className="relative flex-1">
                        <Search size={16} className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="بحث في الطلبات..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="w-full bg-white border border-gray-200 rounded-xl pr-10 pl-4 py-3 text-sm font-medium outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED]"
                        />
                    </div>
                    <select
                        value={statusFilter}
                        onChange={e => setStatusFilter(e.target.value)}
                        className="bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:border-[#7C3AED]"
                    >
                        <option value="all">جميع الحالات</option>
                        <option value="pending">في الانتظار</option>
                        <option value="accepted">مقبول</option>
                        <option value="work_done">منجز</option>
                        <option value="completed">مكتمل</option>
                        <option value="cancelled">ملغي</option>
                    </select>
                </div>

                {/* Table */}
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    {loading ? (
                        <div className="flex items-center justify-center p-20">
                            <Loader2 size={32} className="animate-spin text-[#7C3AED]" />
                        </div>
                    ) : filtered.length === 0 ? (
                        <div className="p-20 text-center">
                            <FileText size={48} className="mx-auto text-gray-200 mb-4" />
                            <p className="text-gray-400 font-bold">{t('requests.noRequests')}</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        {['العنوان', 'العميل', 'المزود', 'الحالة', 'الموقع', 'الإجراءات'].map(h => (
                                            <th key={h} className="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {filtered.map(req => (
                                        <tr key={req.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-6 py-4">
                                                <div>
                                                    <p className="font-bold text-[#1A202C] text-sm">{req.title}</p>
                                                    <p className="text-xs text-gray-400 mt-0.5 line-clamp-1">{req.description}</p>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-2">
                                                    <div className="w-8 h-8 bg-purple-100 text-purple-700 rounded-full flex items-center justify-center text-xs font-black">
                                                        {req.customer?.name?.[0]?.toUpperCase() || 'C'}
                                                    </div>
                                                    <span className="text-sm font-medium text-gray-700">{req.customer?.name || 'غير محدد'}</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="text-sm text-gray-600">{req.provider?.name || '—'}</span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="relative group">
                                                    <select
                                                        value={req.status}
                                                        onChange={(e) => handleQuickStatusUpdate(req.id, e.target.value)}
                                                        disabled={actionLoading === req.id}
                                                        className={`appearance-none px-3 py-1 pr-8 rounded-full text-xs font-bold border cursor-pointer outline-none focus:ring-2 focus:ring-purple-500 transition-all ${STATUS_COLORS[req.status] || 'bg-gray-100 text-gray-600 border-gray-200'} disabled:opacity-50`}
                                                    >
                                                        {Object.entries(STATUS_LABELS).map(([value, label]) => (
                                                            <option key={value} value={value} className="bg-white text-gray-700">{label}</option>
                                                        ))}
                                                    </select>
                                                    <ChevronDown size={12} className="absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none opacity-50" />
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-500 font-mono">
                                                {req.latitude ? `${Number(req.latitude).toFixed(2)}, ${Number(req.longitude).toFixed(2)}` : '—'}
                                            </td>
                                            <td className="px-6 py-4 border-r border-transparent">
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        onClick={() => startEditing(req)}
                                                        title="عرض وتعديل"
                                                        className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors border border-transparent hover:border-blue-100"
                                                    >
                                                        <Edit3 size={18} />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteRequest(req.id)}
                                                        disabled={actionLoading === req.id}
                                                        title="حذف الطلب"
                                                        className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors border border-transparent hover:border-red-100 disabled:opacity-30"
                                                    >
                                                        {actionLoading === req.id ? <Loader2 size={18} className="animate-spin" /> : <Trash2 size={18} />}
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

                {/* Edit Form Modal */}
                {selectedRequest && (
                    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-navy/40 backdrop-blur-sm animate-in fade-in duration-200">
                        <div className="bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden animate-in zoom-in-95 duration-200">
                            {/* Modal Header */}
                            <div className="p-6 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center">
                                        <Edit3 size={20} />
                                    </div>
                                    <h2 className="text-xl font-black text-navy">
                                        تعديل بيانات الطلب #{selectedRequest.id}
                                    </h2>
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        onClick={() => setSelectedRequest(null)}
                                        className="p-2 hover:bg-gray-100 rounded-full transition-colors"
                                    >
                                        <XCircle size={24} className="text-gray-400" />
                                    </button>
                                </div>
                            </div>

                            {/* Modal Body (Always Form) */}
                            <div className="p-8 space-y-8 max-h-[70vh] overflow-y-auto custom-scrollbar">
                                <div className="space-y-6">
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-black text-gray-400 uppercase tracking-widest">موضوع الطلب</label>
                                        <input
                                            type="text"
                                            value={editData.title}
                                            onChange={(e) => setEditData({ ...editData, title: e.target.value })}
                                            className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl font-bold text-navy outline-none focus:border-primary focus:ring-1 focus:ring-primary shadow-inner"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-black text-gray-400 uppercase tracking-widest">وصف الطلب</label>
                                        <textarea
                                            value={editData.description}
                                            onChange={(e) => setEditData({ ...editData, description: e.target.value })}
                                            rows={5}
                                            className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-navy outline-none focus:border-primary focus:ring-1 focus:ring-primary leading-relaxed shadow-inner"
                                        />
                                    </div>
                                    <div className="grid grid-cols-2 gap-6">
                                        <div className="space-y-1.5">
                                            <label className="text-xs font-black text-gray-400 uppercase tracking-widest">الحالة</label>
                                            <select
                                                value={editData.status}
                                                onChange={(e) => setEditData({ ...editData, status: e.target.value })}
                                                className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl font-bold text-navy outline-none focus:border-primary"
                                            >
                                                {Object.entries(STATUS_LABELS).map(([v, l]) => (
                                                    <option key={v} value={v}>{l}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="space-y-1.5">
                                            <label className="text-xs font-black text-gray-400 uppercase tracking-widest">بيانات العميل (عرض فقط)</label>
                                            <div className="p-3 bg-gray-50 border border-gray-100 rounded-xl text-gray-500 font-bold text-sm">
                                                {selectedRequest.customer?.name}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-4 p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                        <div className="flex-1 space-y-1">
                                            <p className="text-[10px] font-black text-gray-400 uppercase">إحداثيات الموقع</p>
                                            <p className="font-mono text-sm text-navy">{editData.latitude}, {editData.longitude}</p>
                                        </div>
                                        <MapPin className="text-primary" size={24} />
                                    </div>
                                </div>
                            </div>

                            {/* Modal Footer */}
                            <div className="p-6 border-t border-gray-100 bg-gray-50/50 flex justify-between items-center gap-3">
                                <button
                                    onClick={() => handleDeleteRequest(selectedRequest.id)}
                                    disabled={!!actionLoading}
                                    className="px-6 py-2.5 rounded-xl bg-red-50 text-red-600 font-bold hover:bg-red-100 transition-all flex items-center gap-2"
                                >
                                    <Trash2 size={18} />
                                    حذف الطلب
                                </button>

                                <div className="flex gap-3">
                                    <button
                                        onClick={() => setSelectedRequest(null)}
                                        disabled={!!actionLoading}
                                        className="px-6 py-2.5 rounded-xl font-bold text-gray-500 hover:bg-white transition-all border border-gray-200"
                                    >
                                        إلغاء
                                    </button>
                                    <button
                                        onClick={handleSaveUpdate}
                                        disabled={!!actionLoading}
                                        className="px-10 py-2.5 rounded-xl bg-primary text-white font-bold hover:bg-primary/90 transition-all flex items-center gap-2 shadow-lg shadow-primary/20"
                                    >
                                        {actionLoading === selectedRequest.id ? <Loader2 size={18} className="animate-spin" /> : <CheckCircle2 size={18} />}
                                        حفظ البيانات
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
