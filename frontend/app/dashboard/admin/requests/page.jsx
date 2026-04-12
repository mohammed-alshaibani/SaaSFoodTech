'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import {
    FileText, RefreshCcw, Clock, CheckCircle2, XCircle,
    Search, Filter, User, MapPin, Calendar, Eye, Loader2
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

    const fetchRequests = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/requests');
            const data = res.data?.data || res.data || [];
            setRequests(data);
            setFiltered(data);
        } catch (err) {
            console.error('Failed to fetch requests:', err);
            setMessage({ type: 'error', text: 'فشل تحميل الطلبات' });
        } finally {
            setLoading(false);
        }
    }, []);

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
                                        {['العنوان', 'العميل', 'المزود', 'الحالة', 'الموقع', 'التاريخ'].map(h => (
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
                                                <span className={`px-3 py-1 rounded-full text-xs font-bold border ${STATUS_COLORS[req.status] || 'bg-gray-100 text-gray-600 border-gray-200'}`}>
                                                    {STATUS_LABELS[req.status] || req.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-500">
                                                {req.latitude ? `${Number(req.latitude).toFixed(2)}, ${Number(req.longitude).toFixed(2)}` : 'لا يوجد'}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-500">
                                                {req.created_at ? new Date(req.created_at).toLocaleDateString('ar-SA') : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}
