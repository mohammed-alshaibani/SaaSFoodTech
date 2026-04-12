'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import {
    Users,
    FileText,
    CheckCircle2,
    Clock,
    ChevronRight,
    ChevronLeft,
    Shield,
    TrendingUp,
    Zap,
    Crown,
    AlertTriangle,
    RefreshCcw,
    CreditCard,
    ChevronDown
} from 'lucide-react';

function Badge({ plan, t }) {
    const base = 'px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider';
    const isPaid = ['paid', 'premium', 'enterprise', 'basic'].includes(plan);
    return isPaid
        ? <span className={`${base} bg-emerald-50 text-emerald-600 border border-emerald-200`}>{t('dashboard.paid') || 'PAID'}</span>
        : <span className={`${base} bg-gray-100 text-gray-500 border border-gray-200`}>{t('dashboard.free') || 'FREE'}</span>;
}

function StatCard({ titleAR, titleEN, value, icon: Icon }) {
    return (
        <div className="bg-white p-6 rounded-3xl border border-gray-100 shadow-[0_4px_20px_rgb(0,0,0,0.03)] relative overflow-hidden group hover:shadow-[0_8px_30px_rgb(124,58,237,0.08)] transition-all duration-300">
            <div className="absolute -right-6 -top-6 w-32 h-32 bg-gradient-to-br from-[#7C3AED]/20 to-transparent rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700" />
            <div className="flex justify-between items-start mb-6">
                <div className="z-10">
                    <h3 className="text-xl font-black text-[#1A202C]">{titleAR}</h3>
                    <p className="text-[10px] font-bold text-gray-400 mt-1 uppercase tracking-widest">{titleEN}</p>
                </div>
                <div className="p-3 bg-[#7C3AED]/10 rounded-2xl text-[#7C3AED] z-10 transition-transform group-hover:scale-110">
                    <Icon size={24} strokeWidth={2.5} />
                </div>
            </div>
            <p className="text-4xl font-black text-[#1A202C] mt-2 relative z-10">{value}</p>
        </div>
    );
}

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
            const errorMsg = err.response?.data?.message || err.message || 'Unknown error';
            const statusCode = err.response?.status;
            console.error(`[AdminDashboard] Error details: Status ${statusCode}, Message: ${errorMsg}`);
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

    const updatePlan = async (user, newPlan) => {
        try {
            await api.patch(`/admin/users/${user.id}/plan`, { plan: newPlan });
            showMessage(`${user.name} ${t('dashboard.planUpdateSuccess') || 'تم تحديث الخطة بنجاح'}`, 'success');
            // Add delay to ensure backend processes the update
            setTimeout(() => {
                fetchUsers(currentPage);
                fetchStats();
            }, 300);
        } catch (err) {
            console.error('Plan update error:', err);
            showMessage(t('dashboard.planUpdateFailed') || 'فشل تحديث الخطة', 'error');
        }
    };

    return (
        <DashboardLayout>
            <div className="space-y-10">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white/50 pb-6 border-b border-gray-100">
                    <div className="flex items-center gap-5">
                        <div className="w-14 h-14 bg-gradient-to-tr from-[#7C3AED] to-purple-400 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-500/20">
                            <Zap size={28} fill="currentColor" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-black text-[#1A202C] tracking-tight">{t('dashboard.adminTitle') || 'إدارة المنصة'}</h1>
                            <p className="text-gray-500 font-medium mt-1">{t('dashboard.insights') || 'نظرة عامة على أداء المنصة'}</p>
                        </div>
                    </div>
                    <button
                        onClick={() => { fetchStats(); fetchUsers(currentPage); }}
                        className="flex items-center justify-center gap-2 px-6 py-3 bg-white border-2 border-[#7C3AED] rounded-full text-sm font-bold text-[#7C3AED] hover:bg-[#7C3AED] hover:text-white transition-all shadow-sm"
                    >
                        <RefreshCcw size={18} /> Sync Data
                    </button>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    {statsLoading ? (
                        [1, 2, 3, 4].map(i => <div key={i} className="h-44 bg-gray-50 rounded-3xl animate-pulse border border-gray-100" />)
                    ) : (
                        <>
                            <StatCard titleAR="حسابات مدفوعة" titleEN="PAID ACCOUNTS" value={stats?.users?.paid || 6} icon={CreditCard} />
                            <StatCard titleAR="إجمالي الطلبات" titleEN="LIFETIME VOLUME" value={stats?.requests?.total || 3} icon={FileText} />
                            <StatCard titleAR="في الانتظار" titleEN="IN QUEUE" value={stats?.requests?.pending || 3} icon={Clock} />
                            <StatCard titleAR="رحلات ناجحة" titleEN="SUCCESSFUL TRIPS" value={stats?.requests?.completed || 0} icon={CheckCircle2} />
                        </>
                    )}
                </div>

                {/* Users Management */}
                <div className="space-y-6 pt-4">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <h2 className="text-2xl font-black text-[#1A202C] flex items-center gap-3">
                            <Shield className="text-[#7C3AED]" size={24} /> {t('dashboard.userManagement') || 'إدارة المستخدمين'}
                        </h2>
                        <div className="flex items-center gap-2 bg-amber-50 px-4 py-2 rounded-full border border-amber-200 relative overflow-hidden">
                            <AlertTriangle size={14} className="text-amber-500 z-10" />
                            <span className="text-[11px] font-bold text-amber-700 uppercase tracking-widest leading-none z-10">Authorization Overrides Active</span>
                        </div>
                    </div>

                    <div className="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm">
                        <div className="overflow-x-auto custom-scrollbar">
                            <table className="w-full text-sm text-left">
                                <thead>
                                    <tr className="bg-gray-50/80 border-b border-gray-200">
                                        {['الاسم', 'البريد الإلكتروني', 'الخطة', 'الدور', 'الإجراءات'].map((h, i) => (
                                            <th key={h} className={`px-8 py-5 text-[#1A202C] text-[10px] font-black uppercase tracking-widest ${i === 0 ? 'min-w-[200px]' : ''}`}>
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {loading ? (
                                        <tr><td colSpan={4} className="p-20 text-center"><div className="animate-spin rounded-full h-10 w-10 border-4 border-[#7C3AED] border-t-transparent mx-auto" /></td></tr>
                                    ) : (
                                        users.map((u, i) => (
                                            <tr key={u.id} className={`transition-colors group ${i % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'} hover:bg-[#7C3AED]/5`}>
                                                <td className="px-8 py-6">
                                                    <div className="flex items-center gap-4">
                                                        <div className="w-12 h-12 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-[#1A202C] font-black shadow-sm group-hover:border-[#7C3AED]/30 group-hover:text-[#7C3AED] transition-colors">
                                                            {u.name?.[0]?.toUpperCase()}
                                                        </div>
                                                        <span className="text-sm font-black text-[#1A202C] tracking-tight truncate">{u.name}</span>
                                                    </div>
                                                </td>
                                                <td className="px-8 py-6">
                                                    <p className="text-[11px] font-bold text-gray-500 truncate mt-0.5">{u.email}</p>
                                                </td>
                                                <td className="px-8 py-6"><Badge plan={u.plan} t={t} /></td>
                                                <td className="px-8 py-6">
                                                    <div className="flex flex-wrap gap-2">
                                                        {(u.roles ?? []).map(r => (
                                                            <span key={r} className="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-[10px] font-bold uppercase tracking-widest">
                                                                {r}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </td>
                                                <td className="px-8 py-6">
                                                    <div className="flex gap-3">
                                                        {dynamicPlans.length > 0 ? (
                                                            <div className="relative">
                                                                <select
                                                                    value={u.plan}
                                                                    onChange={(e) => updatePlan(u, e.target.value)}
                                                                    className="appearance-none pr-8 pl-4 py-2 border border-gray-200 rounded-xl text-[11px] font-black text-gray-700 bg-white outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] cursor-pointer transition uppercase tracking-widest min-w-[120px]"
                                                                >
                                                                    {dynamicPlans.map(plan => (
                                                                        <option key={plan.value} value={plan.value}>{plan.label}</option>
                                                                    ))}
                                                                </select>
                                                                <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                                                    <ChevronDown size={14} />
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <button
                                                                onClick={() => updatePlan(u, u.plan === 'free' ? 'premium' : 'free')}
                                                                className={`flex items-center justify-center gap-2 px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all ${u.plan === 'free' ? 'bg-[#7C3AED] text-white shadow-md shadow-purple-500/20 hover:bg-purple-700' : 'bg-gray-100 text-gray-500 hover:text-[#1A202C] hover:bg-gray-200'}`}
                                                            >
                                                                {u.plan === 'free' ? <Zap size={14} fill="currentColor" /> : <Shield size={14} />}
                                                                {u.plan === 'free' ? t('dashboard.upgrade') || 'UPGRADE' : t('dashboard.downgrade') || 'DOWNGRADE'}
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Pagination */}
                    {meta && meta.last_page > 1 && (
                        <div className="flex flex-col md:flex-row items-center justify-between gap-6 py-4 px-4">
                            <p className="text-[10px] font-bold text-gray-500 uppercase tracking-widest">
                                PAGE {currentPage} OF {meta.last_page} // TOTAL {meta.total} ENTRIES
                            </p>
                            <div className="flex gap-2">
                                <button
                                    disabled={currentPage <= 1}
                                    onClick={() => setCurrentPage(p => p - 1)}
                                    className="w-10 h-10 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-gray-600 disabled:opacity-50 hover:bg-gray-50 transition"
                                >
                                    <ChevronLeft size={20} />
                                </button>
                                <button
                                    disabled={currentPage >= meta.last_page}
                                    onClick={() => setCurrentPage(p => p + 1)}
                                    className="w-10 h-10 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-gray-600 disabled:opacity-50 hover:bg-gray-50 transition"
                                >
                                    <ChevronRight size={20} />
                                </button>
                            </div>
                        </div>
                    )}
                </div>

                {message && (
                    <div className={`fixed bottom-10 right-10 z-[60] px-8 py-4 rounded-2xl text-white text-sm font-bold shadow-xl animate-in slide-in-from-bottom-5 ${message.type === 'error' ? 'bg-red-500' : 'bg-[#7C3AED]'}`}>
                        {message.text}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
