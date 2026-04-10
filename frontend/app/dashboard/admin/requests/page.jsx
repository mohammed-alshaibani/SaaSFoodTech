'use client';

import { useState } from 'react';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import { FileText, RefreshCcw, Clock, CheckCircle2, XCircle } from 'lucide-react';

export default function RequestsDashboard() {
    const { t } = useI18n();

    return (
        <DashboardLayout>
            <div className="space-y-10">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white/50 pb-6 border-b border-gray-100">
                    <div className="flex items-center gap-5">
                        <div className="w-14 h-14 bg-gradient-to-tr from-[#7C3AED] to-purple-400 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-500/20">
                            <FileText size={28} fill="currentColor" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-black text-[#1A202C] tracking-tight">{t('requests.title')}</h1>
                            <p className="text-gray-500 font-medium mt-1">{t('requests.subtitle')}</p>
                        </div>
                    </div>
                    <button className="flex items-center justify-center gap-2 px-6 py-3 bg-white border-2 border-[#7C3AED] rounded-full text-sm font-bold text-[#7C3AED] hover:bg-[#7C3AED] hover:text-white transition-all shadow-sm">
                        <RefreshCcw size={18} /> {t('requests.refresh')}
                    </button>
                </div>

                {/* Stats Row */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
                        <div className="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500">
                            <Clock size={24} />
                        </div>
                        <div>
                            <p className="text-2xl font-black text-[#1A202C]">0</p>
                            <p className="text-xs font-medium text-gray-500">{t('requests.pending')}</p>
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
                        <div className="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500">
                            <CheckCircle2 size={24} />
                        </div>
                        <div>
                            <p className="text-2xl font-black text-[#1A202C]">0</p>
                            <p className="text-xs font-medium text-gray-500">{t('requests.completed')}</p>
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
                        <div className="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center text-red-500">
                            <XCircle size={24} />
                        </div>
                        <div>
                            <p className="text-2xl font-black text-[#1A202C]">0</p>
                            <p className="text-xs font-medium text-gray-500">{t('requests.cancelled')}</p>
                        </div>
                    </div>
                </div>

                {/* Content Placeholder */}
                <div className="bg-white p-20 rounded-3xl border border-gray-100 shadow-sm text-center">
                    <p className="text-gray-500 font-bold">{t('requests.noRequests')}</p>
                </div>
            </div>
        </DashboardLayout>
    );
}
