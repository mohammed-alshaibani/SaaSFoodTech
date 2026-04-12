'use client';

import React from 'react';
import { Crown, ArrowUpRight } from 'lucide-react';
import Link from 'next/link';

export function UpgradeBanner({ t, requestCount, freeLimit }) {
    return (
        <div className="bg-gradient-to-r from-amber-500 to-orange-400 group rounded-3xl p-6 flex flex-col md:flex-row items-center justify-between shadow-lg shadow-amber-500/10 border border-amber-200/50">
            <div className="flex items-center gap-5">
                <div className="w-14 h-14 bg-white/30 backdrop-blur-sm rounded-2xl flex items-center justify-center text-white shadow-inner">
                    <Crown size={28} fill="currentColor" />
                </div>
                <div>
                    <h4 className="text-lg font-black text-white uppercase tracking-wider">{t('dashboard.upgradePro') || 'Upgrade to Pro'}</h4>
                    <p className="text-white/90 font-medium text-sm mt-1">{t('dashboard.usage') || 'Usage'}: {requestCount}/{freeLimit} {t('dashboard.requestsUsed') || 'requests used'}</p>
                </div>
            </div>
            <Link href="/dashboard/customer/plans" className="mt-4 md:mt-0 px-6 py-3 bg-white text-amber-600 rounded-xl text-sm font-bold hover:bg-gray-50 transition active:scale-95 shadow-md flex items-center gap-2">
                {t('dashboard.upgradePro') || 'Upgrade to Pro'} <ArrowUpRight size={18} />
            </Link>
        </div>
    );
}
