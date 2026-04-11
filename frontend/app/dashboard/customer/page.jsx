'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import Link from 'next/link';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useEcho } from '@/hooks/useEcho';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import { format } from 'date-fns';
import {
    Search,
    Filter,
    Plus,
    MapPin,
    Clock,
    Star,
    TrendingUp,
    ArrowUpRight,
    X,
    FileText,
    CheckCircle2,
    LayoutGrid,
    AlertCircle,
    Crown
} from 'lucide-react';

// ─── Stat Card ──────────────────────────────────────────────────────────────
function StatCard({ title, value, icon: Icon, color, t }) {
    const colors = {
        emerald: 'text-emerald-500 bg-emerald-50 border-emerald-200',
        blue: 'text-blue-500 bg-blue-50 border-blue-200',
        amber: 'text-amber-500 bg-amber-50 border-amber-200',
        purple: 'text-[#7C3AED] bg-[#7C3AED]/10 border-[#7C3AED]/20',
    };
    return (
        <div className="bg-white border border-gray-100 p-6 rounded-3xl shadow-[0_4px_20px_rgb(0,0,0,0.03)] hover:shadow-[0_8px_30px_rgb(124,58,237,0.08)] transition-all duration-300 group">
            <div className="flex items-center justify-between mb-4">
                <div className={`p-3 rounded-2xl border ${colors[color] || colors.purple}`}>
                    <Icon size={20} />
                </div>
                <p className="text-[10px] font-black text-gray-400 uppercase tracking-widest">{title}</p>
            </div>
            <p className="text-3xl font-black text-[#1E293B]">{value}</p>
        </div>
    );
}

// ─── Empty State ────────────────────────────────────────────────────────────
function EmptyState({ t }) {
    return (
        <div className="flex flex-col items-center justify-center p-20 text-center gap-6 animate-in fade-in zoom-in duration-500">
            <div className="w-24 h-24 bg-gray-100 rounded-[32px] flex items-center justify-center text-gray-400 border border-gray-200 ring-8 ring-gray-100/50">
                <FileText size={48} />
            </div>
            <div className="max-w-xs space-y-2">
                <h3 className="text-xl font-black text-[#1E293B]">{t('dashboard.noRequests')}</h3>
                <p className="text-sm font-medium text-gray-500 leading-relaxed">{t('dashboard.emptyStateDesc') || 'ابدأ بإنشاء طلبك الأول باستخدام زر الطلبية الجديدة'}</p>
            </div>
        </div>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────
export default function CustomerDashboard() {
    const { user, refreshUser } = useAuth();
    const { t, isRTL } = useI18n();

    const [requests, setRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [formData, setFormData] = useState({ title: '', description: '', latitude: 24.7136, longitude: 46.6753 });
    const [message, setMessage] = useState(null);
    const [enhancing, setEnhancing] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [sortBy, setSortBy] = useState('newest');

    const limitReached = user?.limit_reached ?? false;
    const requestCount = user?.request_count ?? 0;
    const freeLimit = user?.free_limit ?? 3;

    const fetchRequests = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/requests');
            setRequests(res.data.data ?? []);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchRequests();

        // Check for payment success from redirect
        const params = new URLSearchParams(window.location.search);
        if (params.get('payment') === 'success') {
            setMessage({
                text: isRTL ? 'تم تفعيل الاشتراك بنجاح! استمتع بالمميزات الجديدة.' : 'Subscription Activated! Enjoy your new features.',
                type: 'success'
            });
            refreshUser();
            // Clear URL params
            window.history.replaceState({}, document.title, window.location.pathname);
            setTimeout(() => setMessage(null), 5000);
        }
    }, [fetchRequests, refreshUser, isRTL]);

    const filteredRequests = useMemo(() => {
        let filtered = [...requests];
        if (searchTerm) {
            filtered = filtered.filter(req =>
                req.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                req.description.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }
        if (statusFilter !== 'all') {
            filtered = filtered.filter(req => req.status === statusFilter);
        }
        filtered.sort((a, b) => {
            switch (sortBy) {
                case 'newest': return new Date(b.created_at) - new Date(a.created_at);
                case 'oldest': return new Date(a.created_at) - new Date(b.created_at);
                case 'title': return a.title.localeCompare(b.title);
                default: return 0;
            }
        });
        return filtered;
    }, [requests, searchTerm, statusFilter, sortBy]);

    const stats = useMemo(() => ({
        total: requests.length,
        pending: requests.filter(r => r.status === 'pending').length,
        accepted: requests.filter(r => r.status === 'accepted').length,
        completed: requests.filter(r => r.status === 'completed').length,
    }), [requests]);

    useEcho(
        user ? `user.${user.id}` : null,
        'ServiceRequestUpdated',
        useCallback((data) => {
            const updated = data.request;
            if (!updated) return;
            setRequests(prev => {
                const idx = prev.findIndex(r => r.id === updated.id);
                if (idx === -1) return [updated, ...prev];
                const next = [...prev];
                next[idx] = { ...next[idx], ...updated };
                return next;
            });
            setMessage({ text: `${t(`dashboard.${data.action}`)}: "${updated.title}"`, type: 'success' });
            setTimeout(() => setMessage(null), 3000);
        }, [t]),
        [user?.id]
    );

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        try {
            await api.post('/requests', formData);
            setShowForm(false);
            setFormData({ title: '', description: '', latitude: 24.7136, longitude: 46.6753 });
            fetchRequests();
            refreshUser();
            setMessage({ text: t('dashboard.requestCreated') || 'تم إنشاء الطلب', type: 'success' });
        } catch {
            setMessage({ text: t('dashboard.submissionFailed') || 'فشل الإرسال', type: 'error' });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <DashboardLayout>
            <div className="space-y-10">
                {/* Header Section */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-8 bg-white/50 pb-6 border-b border-gray-100">
                    <div className="flex items-center gap-5">
                        <div className="w-14 h-14 bg-gradient-to-tr from-[#7C3AED] to-purple-400 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-500/20 shrink-0">
                            <Star size={28} fill="currentColor" />
                        </div>
                        <div>
                            <div className="flex items-center gap-4">
                                <h1 className="text-3xl font-black text-[#1E293B] tracking-tight">
                                    {t('dashboard.customerTitle')}
                                </h1>
                                <span className={`px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-widest ${user?.plan === 'free' ? 'bg-gray-100 text-gray-500 border border-gray-200' : 'bg-[#7C3AED] text-white shadow-md shadow-purple-500/20'}`}>
                                    {user?.plan === 'free' ? t('dashboard.freePlan') : t('dashboard.proMember')}
                                </span>
                            </div>
                            <p className="text-gray-500 font-medium mt-1">
                                {t('dashboard.manageRequests')}
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={() => setShowForm(v => !v)}
                        disabled={limitReached}
                        className={`flex items-center justify-center gap-2 px-6 py-3 rounded-full text-sm font-bold transition-all shadow-sm
                            ${limitReached
                                ? 'bg-gray-100 text-gray-400 cursor-not-allowed border border-gray-200'
                                : 'bg-[#7C3AED] hover:bg-purple-700 text-white shadow-purple-500/20 active:scale-[0.98]'}`}
                    >
                        {showForm ? <X size={18} /> : <Plus size={18} />}
                        {showForm ? t('dashboard.cancel') : t('dashboard.newRequest')}
                    </button>
                </div>

                {/* Sub Banner */}
                {user?.plan === 'free' && (
                    <div className="bg-gradient-to-r from-amber-500 to-orange-400 group rounded-3xl p-6 flex flex-col md:flex-row items-center justify-between shadow-lg shadow-amber-500/10 border border-amber-200/50">
                        <div className="flex items-center gap-5">
                            <div className="w-14 h-14 bg-white/30 backdrop-blur-sm rounded-2xl flex items-center justify-center text-white shadow-inner">
                                <Crown size={28} fill="currentColor" />
                            </div>
                            <div>
                                <h4 className="text-lg font-black text-white uppercase tracking-wider">{t('dashboard.upgradePro')}</h4>
                                <p className="text-white/90 font-medium text-sm mt-1">{t('dashboard.usage')}: {requestCount}/{freeLimit} {t('dashboard.requestsUsed') || 'طلبات مستخدمة'}</p>
                            </div>
                        </div>
                        <Link href="/subscription" className="mt-4 md:mt-0 px-6 py-3 bg-white text-amber-600 rounded-xl text-sm font-bold hover:bg-gray-50 transition active:scale-95 shadow-md flex items-center gap-2">
                            {t('dashboard.upgradePro')} <ArrowUpRight size={18} />
                        </Link>
                    </div>
                )}

                {/* Stats Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <StatCard title={t('dashboard.totalRequests')} value={stats.total} icon={LayoutGrid} color="purple" t={t} />
                    <StatCard title={t('dashboard.pending')} value={stats.pending} icon={Clock} color="amber" t={t} />
                    <StatCard title={t('dashboard.inProgress')} value={stats.accepted} icon={TrendingUp} color="blue" t={t} />
                    <StatCard title={t('dashboard.complete')} value={stats.completed} icon={CheckCircle2} color="emerald" t={t} />
                </div>

                {/* Filters Row */}
                <div className="flex flex-col lg:flex-row gap-4 items-center">
                    <div className={`flex-1 relative w-full translate-z-0 ${isRTL ? 'rtl' : ''}`}>
                        <Search className={`absolute top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 ${isRTL ? 'right-4 left-auto' : 'left-4'}`} />
                        <input
                            type="text"
                            placeholder={t('dashboard.searchPlaceholder')}
                            value={searchTerm}
                            onChange={e => setSearchTerm(e.target.value)}
                            className={`w-full bg-white border border-gray-200 rounded-2xl py-4 text-sm font-bold text-[#1E293B] outline-none hover:border-gray-300 focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] transition shadow-sm ${isRTL ? 'pr-12 pl-4' : 'pl-12 pr-4'}`}
                        />
                    </div>
                    <div className="flex gap-3 w-full lg:w-auto">
                        <select
                            value={statusFilter}
                            onChange={e => setStatusFilter(e.target.value)}
                            className="flex-1 lg:w-48 bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold text-gray-600 outline-none hover:border-gray-300 focus:border-[#7C3AED] appearance-none shadow-sm"
                        >
                            <option value="all">{t('dashboard.allStatuses') || 'جميع الحالات'}</option>
                            <option value="pending">{t('dashboard.pending')}</option>
                            <option value="accepted">{t('dashboard.accepted')}</option>
                            <option value="completed">{t('dashboard.complete')}</option>
                        </select>
                        <button className="px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 hover:text-[#7C3AED] hover:border-[#7C3AED]/30 transition shadow-sm flex items-center gap-2">
                            <Filter size={16} /> {t('dashboard.filters')}
                        </button>
                    </div>
                </div>

                {/* User Request List */}
                <div className="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden min-h-[400px]">
                    {loading ? (
                        <div className="flex items-center justify-center p-40">
                            <div className="animate-spin rounded-full h-12 w-12 border-4 border-[#7C3AED] border-t-transparent" />
                        </div>
                    ) : filteredRequests.length === 0 ? (
                        <EmptyState t={t} />
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {filteredRequests.map(req => (
                                <div key={req.id} className="p-6 hover:bg-gray-50/50 transition-all cursor-pointer group">
                                    <div className="flex flex-col md:flex-row justify-between gap-6">
                                        <div className="space-y-3 flex-1">
                                            <div className="flex items-center gap-3">
                                                <h3 className="text-lg font-bold text-[#1E293B] group-hover:text-[#7C3AED] transition-colors">{req.title}</h3>
                                                <span className={`px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider ${req.status === 'pending' ? 'bg-amber-100 text-amber-600 border border-amber-200' : req.status === 'completed' ? 'bg-emerald-100 text-emerald-600 border border-emerald-200' : 'bg-[#7C3AED]/10 text-[#7C3AED] border border-[#7C3AED]/20'}`}>
                                                    {t(`dashboard.${req.status}`)}
                                                </span>
                                            </div>
                                            <p className="text-gray-500 font-medium line-clamp-2 max-w-3xl leading-relaxed text-sm">{req.description}</p>
                                            <div className="flex flex-wrap gap-3 pt-1">
                                                <div className="flex items-center gap-2 px-3 py-1.5 bg-gray-100 rounded-lg border border-gray-200">
                                                    <Clock size={14} className="text-[#7C3AED]" />
                                                    <span className="text-[11px] font-bold text-gray-500">
                                                        {req.created_at ? format(new Date(req.created_at), 'yyyy/MM/dd') : 'N/A'}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2 px-3 py-1.5 bg-gray-100 rounded-lg border border-gray-200">
                                                    <MapPin size={14} className="text-[#7C3AED]" />
                                                    <span className="text-[11px] font-bold text-gray-500">{req.latitude?.toFixed(2)}, {req.longitude?.toFixed(2)}</span>
                                                </div>
                                            </div>
                                        </div>
                                        {req.provider && (
                                            <div className="flex items-center gap-3 bg-[#7C3AED]/5 p-3 rounded-2xl border border-[#7C3AED]/10 h-fit">
                                                <div className="w-10 h-10 bg-[#7C3AED] rounded-xl flex items-center justify-center text-white font-bold shadow-md shadow-purple-500/20 uppercase">
                                                    {req.provider.name?.[0]}
                                                </div>
                                                <div>
                                                    <p className="text-[10px] font-bold text-[#7C3AED]/70 uppercase tracking-wider">{t('dashboard.provider') || 'المزود'}</p>
                                                    <p className="text-sm font-bold text-[#1E293B]">{req.provider.name}</p>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Submit Toast */}
                {message && (
                    <div className={`fixed bottom-10 ${isRTL ? 'left-10' : 'right-10'} z-[60] px-6 py-4 rounded-2xl text-sm font-bold shadow-xl animate-in slide-in-from-bottom-5 ${message.type === 'error' ? 'bg-red-500 text-white' : 'bg-[#7C3AED] text-white'}`}>
                        {message.text}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
