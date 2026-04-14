'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useEcho } from '@/hooks/useEcho';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import {
    Search,
    Filter,
    Clock,
    TrendingUp,
    CheckCircle2,
    LayoutGrid,
    FileText
} from 'lucide-react';

// Common Components
import { StatCard } from '@/components/common/StatCard';

// Specialized Customer Components
import { CustomerHeader } from '@/components/customer/CustomerHeader';
import { UpgradeBanner } from '@/components/customer/UpgradeBanner';
import { CustomerRequestRow } from '@/components/customer/CustomerRequestRow';

function EmptyState({ t }) {
    return (
        <div className="flex flex-col items-center justify-center p-20 text-center gap-6 animate-in fade-in zoom-in duration-500">
            <div className="w-24 h-24 bg-gray-100 rounded-[32px] flex items-center justify-center text-gray-400 border border-gray-200 ring-8 ring-gray-100/50">
                <FileText size={48} />
            </div>
            <div className="max-w-xs space-y-2">
                <h3 className="text-xl font-black text-charcoal">{t('dashboard.noRequests')}</h3>
                <p className="text-sm font-medium text-gray-500 leading-relaxed">{t('dashboard.emptyStateDesc') || 'ابدأ بإنشاء طلبك الأول باستخدام زر الطلبية الجديدة'}</p>
            </div>
        </div>
    );
}

export default function CustomerDashboard() {
    const { user, refreshUser } = useAuth();
    const { t, isRTL } = useI18n();

    const [requests, setRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState(null);

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

        const params = new URLSearchParams(window.location.search);
        if (params.get('payment') === 'success') {
            setMessage({
                text: isRTL ? 'تم تفعيل الاشتراك بنجاح!' : 'Subscription Activated!',
                type: 'success'
            });
            refreshUser();
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

    return (
        <DashboardLayout>
            <div className="space-y-10">
                <CustomerHeader user={user} t={t} limitReached={limitReached} />

                {user?.plan === 'free' && (
                    <UpgradeBanner t={t} requestCount={requestCount} freeLimit={freeLimit} />
                )}

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <StatCard title={t('dashboard.totalRequests')} value={stats.total} icon={LayoutGrid} color="purple" />
                    <StatCard title={t('dashboard.pending')} value={stats.pending} icon={Clock} color="amber" />
                    <StatCard title={t('dashboard.inProgress')} value={stats.accepted} icon={TrendingUp} color="blue" />
                    <StatCard title={t('dashboard.complete')} value={stats.completed} icon={CheckCircle2} color="emerald" />
                </div>

                <div className="flex flex-col lg:flex-row gap-4 items-center">
                    <div className="flex-1 relative w-full translate-z-0">
                        <Search className={`absolute top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 ${isRTL ? 'right-4 left-auto' : 'left-4'}`} />
                        <input
                            type="text"
                            placeholder={t('dashboard.searchPlaceholder')}
                            value={searchTerm}
                            onChange={e => setSearchTerm(e.target.value)}
                            className={`w-full bg-white border border-gray-200 rounded-2xl py-4 text-sm font-bold text-charcoal outline-none hover:border-gray-300 focus:border-primary focus:ring-1 focus:ring-primary transition shadow-sm ${isRTL ? 'pr-12 pl-4' : 'pl-12 pr-4'}`}
                        />
                    </div>
                    <div className="flex gap-3 w-full lg:w-auto">
                        <select
                            value={statusFilter}
                            onChange={e => setStatusFilter(e.target.value)}
                            className="flex-1 lg:w-48 bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold text-gray-600 outline-none hover:border-gray-300 focus:border-primary appearance-none shadow-sm"
                        >
                            <option value="all">{t('dashboard.allStatuses') || 'جميع الحالات'}</option>
                            <option value="pending">{t('dashboard.pending')}</option>
                            <option value="accepted">{t('dashboard.accepted')}</option>
                            <option value="completed">{t('dashboard.complete')}</option>
                        </select>
                        <button className="px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 hover:text-primary hover:border-primary/30 transition shadow-sm flex items-center gap-2">
                            <Filter size={16} /> {t('dashboard.filters')}
                        </button>
                    </div>
                </div>

                <div className="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden min-h-[400px]">
                    {loading ? (
                        <div className="flex items-center justify-center p-40">
                            <div className="animate-spin rounded-full h-12 w-12 border-4 border-primary border-t-transparent" />
                        </div>
                    ) : filteredRequests.length === 0 ? (
                        <EmptyState t={t} />
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {filteredRequests.map(req => (
                                <CustomerRequestRow key={req.id} req={req} t={t} />
                            ))}
                        </div>
                    )}
                </div>

                {message && (
                    <div className={`fixed bottom-10 ${isRTL ? 'left-10' : 'right-10'} z-[60] px-6 py-4 rounded-2xl text-sm font-bold shadow-xl animate-in slide-in-from-bottom-5 ${message.type === 'error' ? 'bg-red-500 text-white' : 'bg-primary text-white'}`}>
                        {message.text}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
