'use client';

import React from 'react';
import { Shield, Zap, ChevronDown } from 'lucide-react';

function Badge({ plan, t }) {
    const base = 'px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider';
    const isPaid = ['paid', 'premium', 'enterprise', 'basic'].includes(plan);
    return isPaid
        ? <span className={`${base} bg-emerald-50 text-emerald-600 border border-emerald-200`}>{t('dashboard.paid') || 'PAID'}</span>
        : <span className={`${base} bg-gray-100 text-gray-500 border border-gray-200`}>{t('dashboard.free') || 'FREE'}</span>;
}

export function TableSkeleton({ rows = 5 }) {
    return (
        <div className="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm">
            <div className="animate-pulse">
                <div className="h-16 bg-gray-50 border-b border-gray-200" />
                {[...Array(rows)].map((_, i) => (
                    <div key={i} className="h-20 border-b border-gray-100 flex items-center px-8 gap-4">
                        <div className="w-12 h-12 bg-gray-200 rounded-xl" />
                        <div className="flex-1 space-y-2">
                            <div className="h-3 bg-gray-200 rounded w-1/4" />
                            <div className="h-2 bg-gray-100 rounded w-1/2" />
                        </div>
                        <div className="w-24 h-8 bg-gray-200 rounded-xl" />
                        <div className="w-24 h-8 bg-gray-100 rounded-xl" />
                    </div>
                ))}
            </div>
        </div>
    );
}

export function AdminUserTable({ users, t, dynamicPlans, onUpdatePlan }) {
    const headers = [
        t('permissions.name') || 'الاسم',
        t('permissions.email') || 'البريد الإلكتروني',
        t('permissions.plan') || 'الخطة',
        t('permissions.role') || 'الدور',
        t('common.actions') || 'الإجراءات'
    ];

    return (
        <div className="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm">
            <div className="overflow-x-auto custom-scrollbar">
                <table className="w-full text-sm text-left">
                    <thead>
                        <tr className="bg-gray-50/80 border-b border-gray-200">
                            {headers.map((h, i) => (
                                <th key={i} className={`px-8 py-5 text-charcoal text-[10px] font-black uppercase tracking-widest ${i === 0 ? 'min-w-[200px]' : ''}`}>
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {users.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="p-20 text-center text-gray-400 font-bold uppercase tracking-widest">
                                    {t('common.noData') || 'No Data'}
                                </td>
                            </tr>
                        ) : (
                            users.map((u, i) => (
                                <tr key={u.id} className={`transition-colors group ${i % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'} hover:bg-primary/5`}>
                                    <td className="px-8 py-6">
                                        <div className="flex items-center gap-4">
                                            <div className="w-12 h-12 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-charcoal font-black shadow-sm group-hover:border-primary/30 group-hover:text-primary transition-colors">
                                                {u.name?.[0]?.toUpperCase()}
                                            </div>
                                            <span className="text-sm font-black text-charcoal tracking-tight truncate">{u.name}</span>
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
                                                        onChange={(e) => onUpdatePlan(u, e.target.value)}
                                                        className="appearance-none pr-8 pl-4 py-2 border border-gray-200 rounded-xl text-[11px] font-black text-gray-700 bg-white outline-none focus:border-primary focus:ring-1 focus:ring-primary cursor-pointer transition uppercase tracking-widest min-w-[120px]"
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
                                                    onClick={() => onUpdatePlan(u, u.plan === 'free' ? 'premium' : 'free')}
                                                    className={`flex items-center justify-center gap-2 px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all ${u.plan === 'free' ? 'bg-primary text-white shadow-md shadow-purple-500/20 hover:bg-purple-700' : 'bg-gray-100 text-gray-500 hover:text-charcoal hover:bg-gray-200'}`}
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
    );
}
