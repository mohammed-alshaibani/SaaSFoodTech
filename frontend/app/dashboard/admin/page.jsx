'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import {
    FileText,
    CheckCircle2,
    Clock,
    Shield,
    AlertTriangle,
    CreditCard
} from 'lucide-react';

// Common Components
import { StatCard, StatSkeleton } from '@/components/common/StatCard';

// Specialized Admin Components
import { AdminHeader } from '@/components/admin/AdminHeader';
import { AdminUserTable, TableSkeleton } from '@/components/admin/AdminUserTable';
import { Pagination } from '@/components/admin/Pagination';

export default function AdminDashboard() {
    const { t, isRTL } = useI18n();
    const [users, setUsers] = useState([]);
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [statsLoading, setStatsLoading] = useState(true);
    const [message, setMessage] = useState(null);
    const [currentPage, setCurrentPage] = useState(1);
    const [meta, setMeta] = useState(null);
    const [dynamicPlans, setDynamicPlans] = useState([]);

    const showMessage = useCallback((text, type = 'info') => {
        setMessage({ text, type });
        setTimeout(() => setMessage(null), 3500);
    }, []);

    useEffect(() => {
        api.get('/subscription/plans').then(res => {
            const plans = (res.data.data || []).map(p => ({ value: p.name, label: p.display_name || p.name }));
            if (plans.length > 0) setDynamicPlans(plans);
        }).catch(err => console.error("Failed to fetch dynamic plans", err));
    }, []);

    const fetchUsers = useCallback(async (page = 1) => {
        setLoading(true);
        try {
            const res = await api.get(`/admin/users?page=${page}&per_page=100`);
            setUsers(res.data.data);
            setMeta(res.data.meta);
        } catch (err) {
            console.error('[AdminDashboard] Failed to load users:', err);
            const statusCode = err.response?.status;
            showMessage(`${t('permissions.failedToLoad') || 'فشل تحميل البيانات'} ${statusCode ? `(${statusCode})` : ''}`, 'error');
        } finally {
            setLoading(false);
        }
    }, [showMessage, t]);

    const fetchStats = useCallback(async () => {
        setStatsLoading(true);
        try {
            const res = await api.get('/admin/stats');
            setStats(res.data.data);
        } catch (err) {
            console.error('[AdminDashboard] Failed to load stats:', err);
        } finally {
            setStatsLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchUsers(currentPage);
        fetchStats();
    }, [currentPage, fetchUsers, fetchStats]);

    const handleUpdatePlan = async (user, newPlan) => {
        try {
            await api.patch(`/admin/users/${user.id}/plan`, { plan: newPlan });
            showMessage(`${user.name} ${t('dashboard.planUpdateSuccess') || 'تم تحديث الخطة بنجاح'}`, 'success');
            setTimeout(() => {
                fetchUsers(currentPage);
                fetchStats();
            }, 300);
        } catch (err) {
            console.error('Plan update error:', err);
            showMessage(t('dashboard.planUpdateFailed') || 'فشل تحديث الخطة', 'error');
        }
    };

    const handleSync = () => {
        fetchStats();
        fetchUsers(currentPage);
    };

    return (
        <DashboardLayout>
            <div className="space-y-10">
                <AdminHeader
                    title={t('dashboard.adminTitle') || 'إدارة المنصة'}
                    subtitle={t('dashboard.insightsAdmin') || 'نظرة عامة على أداء المنصة'}
                    onSync={handleSync}
                    isSyncing={loading || statsLoading}
                />

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    {statsLoading ? (
                        [1, 2, 3, 4].map(i => <StatSkeleton key={i} />)
                    ) : (
                        <>
                            <StatCard title={isRTL ? "حسابات مدفوعة" : "Paid Accounts"} subtitle="Premium Tier" value={stats?.users?.paid || 0} icon={CreditCard} color="purple" />
                            <StatCard title={isRTL ? "إجمالي الطلبات" : "Total Requests"} subtitle="Platform Volume" value={stats?.requests?.total || 0} icon={FileText} color="blue" />
                            <StatCard title={isRTL ? "في الانتظار" : "Pending"} subtitle="Needs Action" value={stats?.requests?.pending || 0} icon={Clock} color="amber" />
                            <StatCard title={isRTL ? "رحلات ناجحة" : "Successful Trips"} subtitle="Completed" value={stats?.requests?.completed || 0} icon={CheckCircle2} color="emerald" />
                        </>
                    )}
                </div>

                <div className="space-y-6 pt-4">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <h2 className="text-2xl font-black text-charcoal flex items-center gap-3">
                            <Shield className="text-primary" size={24} /> {t('dashboard.userManagement') || 'إدارة المستخدمين'}
                        </h2>
                        <div className="flex items-center gap-2 bg-amber-50 px-4 py-2 rounded-full border border-amber-200 relative overflow-hidden">
                            <AlertTriangle size={14} className="text-amber-500 z-10" />
                            <span className="text-[11px] font-bold text-amber-700 uppercase tracking-widest leading-none z-10">Authorization Overrides Active</span>
                        </div>
                    </div>

                    {loading ? (
                        <TableSkeleton rows={8} />
                    ) : (
                        <AdminUserTable
                            users={users}
                            loading={loading}
                            t={t}
                            dynamicPlans={dynamicPlans}
                            onUpdatePlan={handleUpdatePlan}
                        />
                    )}

                    <Pagination
                        currentPage={currentPage}
                        totalPages={meta?.last_page || 0}
                        onPageChange={setCurrentPage}
                        totalEntries={meta?.total || 0}
                    />
                </div>

                {message && (
                    <div className={`fixed bottom-10 right-10 z-[60] px-8 py-4 rounded-2xl text-white text-sm font-bold shadow-xl animate-in slide-in-from-bottom-5 ${message.type === 'error' ? 'bg-red-500' : 'bg-primary'}`}>
                        {message.text}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
