'use client';

import { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import { format } from 'date-fns';
import {
    Search,
    Filter,
    Plus,
    MapPin,
    Clock,
    ArrowUpRight,
    FileText,
    CheckCircle2,
    AlertCircle,
    Loader2,
    ChevronRight,
    X,
    Edit2,
    Trash2
} from 'lucide-react';

const STATUS_BADGES = {
    pending: { bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200', label: 'Pending', labelAr: 'معلق' },
    accepted: { bg: 'bg-indigo-50', text: 'text-indigo-700', border: 'border-indigo-200', label: 'In Progress', labelAr: 'قيد التنفيذ' },
    completed: { bg: 'bg-emerald-50', text: 'text-emerald-700', border: 'border-emerald-200', label: 'Completed', labelAr: 'مكتمل' },
    cancelled: { bg: 'bg-red-50', text: 'text-red-700', border: 'border-red-200', label: 'Cancelled', labelAr: 'ملغي' },
};

// ─── Empty State ────────────────────────────────────────────────────────────
function EmptyState({ isRTL }) {
    return (
        <div className="flex flex-col items-center justify-center p-20 text-center gap-6 animate-in fade-in zoom-in duration-500">
            <div className="w-24 h-24 bg-gray-100 rounded-[32px] flex items-center justify-center text-gray-400 border border-gray-200 ring-8 ring-gray-100/50">
                <FileText size={48} />
            </div>
            <div className="max-w-xs space-y-2">
                <h3 className="text-xl font-black text-[#1E293B]">
                    {isRTL ? 'لا توجد طلبات' : 'No Requests Found'}
                </h3>
                <p className="text-sm font-medium text-gray-500 leading-relaxed">
                    {isRTL
                        ? 'ابدأ بإنشاء طلبك الأول باستخدام زر الطلبية الجديدة'
                        : 'Start by creating your first request using the New Request button'}
                </p>
            </div>
        </div>
    );
}

// ─── Request Card ───────────────────────────────────────────────────────────
function RequestCard({ request, isRTL, onEdit, onDelete }) {
    const status = STATUS_BADGES[request.status] || STATUS_BADGES.pending;
    const label = isRTL ? status.labelAr : status.label;

    return (
        <div className="bg-white border border-gray-100 rounded-3xl p-6 hover:shadow-lg hover:border-[#7C3AED]/20 transition-all duration-300 group">
            <div className="flex items-start justify-between gap-4 mb-4">
                <div className="flex-1 min-w-0">
                    <h3 className="font-bold text-[#1E293B] text-lg truncate group-hover:text-[#7C3AED] transition-colors">
                        {request.title}
                    </h3>
                    <p className="text-sm text-gray-500 mt-1 line-clamp-2">{request.description}</p>
                </div>
                <span
                    className={`px-3 py-1 rounded-full text-xs font-bold border ${status.bg} ${status.text} ${status.border} shrink-0`}
                >
                    {label}
                </span>
            </div>

            <div className="flex items-center gap-6 text-sm text-gray-500 mb-4">
                <div className="flex items-center gap-2">
                    <Clock size={14} />
                    <span>{format(new Date(request.created_at), 'MMM d, yyyy')}</span>
                </div>
                {request.provider && (
                    <div className="flex items-center gap-2">
                        <CheckCircle2 size={14} className="text-emerald-500" />
                        <span>
                            {isRTL ? 'الموفر: ' : 'Provider: '} {request.provider.name}
                        </span>
                    </div>
                )}
            </div>

            <div className="flex items-center justify-between pt-4 border-t border-gray-100">
                <div className="flex items-center gap-2 text-sm text-gray-500">
                    <MapPin size={14} className="text-[#7C3AED]" />
                    <span className="truncate max-w-[200px]">
                        {request.latitude?.toFixed(4)}, {request.longitude?.toFixed(4)}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <button onClick={() => onEdit(request)} className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                        <Edit2 size={16} />
                    </button>
                    <button onClick={() => onDelete(request.id)} className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                        <Trash2 size={16} />
                    </button>
                    <Link
                        href={`/dashboard/customer/requests/${request.id}`}
                        className="flex items-center gap-1 text-sm font-bold text-[#7C3AED] hover:text-[#6D28D9] transition-colors"
                    >
                        {isRTL ? 'عرض التفاصيل' : 'View Details'}
                        <ChevronRight size={16} className={isRTL ? 'rotate-180' : ''} />
                    </Link>
                </div>
            </div>
        </div>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────
export default function RequestsPage() {
    const { user } = useAuth();
    const { t, isRTL } = useI18n();

    const [requests, setRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [showModal, setShowModal] = useState(false);
    const [editingRequest, setEditingRequest] = useState(null);
    const [formData, setFormData] = useState({ title: '', description: '', latitude: '', longitude: '' });
    const [message, setMessage] = useState(null);

    const fetchRequests = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/requests');
            setRequests(res.data.data ?? []);
        } catch (err) {
            console.error('[RequestsPage] Failed to load requests:', err);
        } finally {
            setLoading(false);
        }
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (editingRequest) {
                await api.put(`/requests/${editingRequest.id}`, formData);
                setMessage({ type: 'success', text: t('requests.updateSuccess') || 'Request updated successfully' });
            } else {
                await api.post('/requests', formData);
                setMessage({ type: 'success', text: t('requests.createSuccess') || 'Request created successfully' });
            }
            setShowModal(false);
            setEditingRequest(null);
            setFormData({ title: '', description: '', latitude: '', longitude: '' });
            fetchRequests();
        } catch (err) {
            console.error('Failed to save request:', err);
            setMessage({ type: 'error', text: t('requests.saveFailed') || 'Failed to save request' });
        }
    };

    const handleEdit = (request) => {
        setEditingRequest(request);
        setFormData({ 
            title: request.title, 
            description: request.description, 
            latitude: request.latitude || '', 
            longitude: request.longitude || '' 
        });
        setShowModal(true);
    };

    const handleDelete = async (id) => {
        if (!confirm(t('requests.confirmDelete') || 'Are you sure you want to delete this request?')) return;
        try {
            await api.delete(`/requests/${id}`);
            setMessage({ type: 'success', text: t('requests.deleteSuccess') || 'Request deleted successfully' });
            fetchRequests();
        } catch (err) {
            console.error('Failed to delete request:', err);
            setMessage({ type: 'error', text: t('requests.deleteFailed') || 'Failed to delete request' });
        }
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingRequest(null);
        setFormData({ title: '', description: '', latitude: '', longitude: '' });
    };

    useEffect(() => {
        fetchRequests();
    }, [fetchRequests]);

    const filteredRequests = requests.filter((req) => {
        const matchesSearch =
            searchTerm === '' ||
            req.title?.toLowerCase().includes(searchTerm.toLowerCase()) ||
            req.description?.toLowerCase().includes(searchTerm.toLowerCase());
        const matchesStatus = statusFilter === 'all' || req.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    const stats = {
        total: requests.length,
        pending: requests.filter((r) => r.status === 'pending').length,
        inProgress: requests.filter((r) => r.status === 'accepted').length,
        completed: requests.filter((r) => r.status === 'completed').length,
    };

    return (
        <DashboardLayout>
            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black text-[#1E293B]">
                            {isRTL ? 'طلباتي' : 'My Requests'}
                        </h1>
                        <p className="text-gray-500 mt-1">
                            {isRTL ? 'إدارة ومراقبة جميع طلباتك' : 'Manage and track all your service requests'}
                        </p>
                    </div>
                    <button
                        onClick={() => setShowModal(true)}
                        className="flex items-center gap-2 px-6 py-3 bg-[#7C3AED] text-white font-bold rounded-2xl hover:bg-[#6D28D9] transition-all shadow-lg shadow-[#7C3AED]/25"
                    >
                        <Plus size={20} />
                        {isRTL ? 'طلب جديد' : 'New Request'}
                    </button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white border border-gray-100 p-5 rounded-2xl">
                        <p className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">
                            {isRTL ? 'الإجمالي' : 'Total'}
                        </p>
                        <p className="text-2xl font-black text-[#1E293B]">{stats.total}</p>
                    </div>
                    <div className="bg-white border border-gray-100 p-5 rounded-2xl">
                        <p className="text-xs font-bold text-amber-600 uppercase tracking-wider mb-1">
                            {isRTL ? 'معلق' : 'Pending'}
                        </p>
                        <p className="text-2xl font-black text-amber-600">{stats.pending}</p>
                    </div>
                    <div className="bg-white border border-gray-100 p-5 rounded-2xl">
                        <p className="text-xs font-bold text-indigo-600 uppercase tracking-wider mb-1">
                            {isRTL ? 'قيد التنفيذ' : 'In Progress'}
                        </p>
                        <p className="text-2xl font-black text-indigo-600">{stats.inProgress}</p>
                    </div>
                    <div className="bg-white border border-gray-100 p-5 rounded-2xl">
                        <p className="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">
                            {isRTL ? 'مكتمل' : 'Completed'}
                        </p>
                        <p className="text-2xl font-black text-emerald-600">{stats.completed}</p>
                    </div>
                </div>

                {/* Filters */}
                <div className="flex flex-col md:flex-row gap-4">
                    <div className={`flex-1 relative ${isRTL ? 'rtl' : ''}`}>
                        <Search
                            className={`absolute top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 ${
                                isRTL ? 'right-4 left-auto' : 'left-4'
                            }`}
                        />
                        <input
                            type="text"
                            placeholder={isRTL ? 'البحث في الطلبات...' : 'Search requests...'}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className={`w-full bg-white border border-gray-200 rounded-2xl py-4 text-sm font-bold text-[#1E293B] outline-none hover:border-gray-300 focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition shadow-sm ${
                                isRTL ? 'pr-12 pl-4' : 'pl-12 pr-4'
                            }`}
                        />
                    </div>
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="md:w-48 bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-gray-600 outline-none hover:border-gray-300 focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] shadow-sm"
                    >
                        <option value="all">{isRTL ? 'جميع الحالات' : 'All Statuses'}</option>
                        <option value="pending">{isRTL ? 'معلق' : 'Pending'}</option>
                        <option value="accepted">{isRTL ? 'قيد التنفيذ' : 'In Progress'}</option>
                        <option value="completed">{isRTL ? 'مكتمل' : 'Completed'}</option>
                    </select>
                </div>

                {/* Requests List */}
                <div className="bg-white border border-gray-100 rounded-[32px] shadow-[0_4px_20px_rgb(0,0,0,0.03)]">
                    {loading ? (
                        <div className="flex items-center justify-center p-20">
                            <Loader2 className="animate-spin h-8 w-8 text-[#7C3AED]" />
                        </div>
                    ) : filteredRequests.length === 0 ? (
                        <EmptyState isRTL={isRTL} />
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {filteredRequests.map((request) => (
                                <div key={request.id} className="p-6">
                                    <RequestCard request={request} isRTL={isRTL} onEdit={handleEdit} onDelete={handleDelete} />
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Message */}
                {message && (
                    <div
                        className={`p-4 rounded-2xl text-sm font-bold ${
                            message.type === 'success'
                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                : 'bg-red-50 text-red-700 border border-red-200'
                        }`}
                    >
                        {message.text}
                    </div>
                )}

                {/* Modal */}
                {showModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-3xl p-8 w-full max-w-md shadow-2xl">
                            <div className="flex items-center justify-between mb-6">
                                <h2 className="text-2xl font-black text-[#1E293B]">
                                    {editingRequest ? (isRTL ? 'تعديل الطلب' : 'Edit Request') : (isRTL ? 'طلب جديد' : 'New Request')}
                                </h2>
                                <button onClick={closeModal} className="p-2 hover:bg-gray-100 rounded-xl transition">
                                    <X size={20} />
                                </button>
                            </div>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{isRTL ? 'العنوان' : 'Title'}</label>
                                    <input
                                        type="text"
                                        value={formData.title}
                                        onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1E293B] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{isRTL ? 'الوصف' : 'Description'}</label>
                                    <textarea
                                        value={formData.description}
                                        onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1E293B] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition resize-none"
                                        rows={3}
                                        required
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{isRTL ? 'خط العرض' : 'Latitude'}</label>
                                        <input
                                            type="text"
                                            value={formData.latitude}
                                            onChange={(e) => setFormData({ ...formData, latitude: e.target.value })}
                                            className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1E293B] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{isRTL ? 'خط الطول' : 'Longitude'}</label>
                                        <input
                                            type="text"
                                            value={formData.longitude}
                                            onChange={(e) => setFormData({ ...formData, longitude: e.target.value })}
                                            className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-[#1E293B] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition"
                                        />
                                    </div>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button type="button" onClick={closeModal} className="flex-1 px-6 py-3 bg-gray-100 rounded-full text-sm font-bold text-gray-600 hover:bg-gray-200 transition">
                                        {isRTL ? 'إلغاء' : 'Cancel'}
                                    </button>
                                    <button type="submit" className="flex-1 px-6 py-3 bg-[#7C3AED] rounded-full text-sm font-bold text-white hover:bg-[#6D28D9] transition">
                                                {editingRequest ? (isRTL ? 'تحديث' : 'Update') : (isRTL ? 'إنشاء' : 'Create')}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Results Count */}
                {!loading && (
                    <p className="text-xs text-gray-400 font-medium">
                        {isRTL
                            ? `عرض ${filteredRequests.length} من ${requests.length} طلب`
                            : `Showing ${filteredRequests.length} of ${requests.length} requests`}
                    </p>
                )}
            </div>
        </DashboardLayout>
    );
}
