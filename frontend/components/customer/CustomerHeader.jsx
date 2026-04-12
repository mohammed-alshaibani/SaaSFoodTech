'use client';

import React from 'react';
import { Star, Plus } from 'lucide-react';
import Link from 'next/link';

export function CustomerHeader({ user, t, limitReached }) {
    return (
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-8 bg-white/50 pb-6 border-b border-gray-100">
            <div className="flex items-center gap-5">
                <div className="w-14 h-14 bg-gradient-to-tr from-primary to-purple-400 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-500/20 shrink-0">
                    <Star size={28} fill="currentColor" />
                </div>
                <div>
                    <div className="flex items-center gap-4">
                        <h1 className="text-3xl font-black text-charcoal tracking-tight">
                            {t('dashboard.customerTitle') || 'Customer Dashboard'}
                        </h1>
                        <span className={`px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-widest ${user?.plan === 'free' ? 'bg-gray-100 text-gray-500 border border-gray-200' : 'bg-primary text-white shadow-md shadow-purple-500/20'}`}>
                            {user?.plan === 'free' ? t('dashboard.freePlan') || 'FREE' : t('dashboard.proMember') || 'PRO'}
                        </span>
                    </div>
                    <p className="text-gray-500 font-medium mt-1">
                        {t('dashboard.manageRequests') || 'Manage your service requests.'}
                    </p>
                </div>
            </div>
            <Link
                href="/dashboard/customer/requests"
                className={`flex items-center justify-center gap-2 px-6 py-3 rounded-full text-sm font-bold transition-all shadow-sm
                    ${limitReached
                        ? 'bg-gray-100 text-gray-400 cursor-not-allowed border border-gray-200 pointer-events-none'
                        : 'bg-primary hover:bg-purple-700 text-white shadow-purple-500/20 active:scale-[0.98]'}`}
            >
                <Plus size={18} />
                {t('dashboard.newRequest') || 'New Request'}
            </Link>
        </div>
    );
}
