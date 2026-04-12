'use client';

import React from 'react';
import { Zap, RefreshCcw } from 'lucide-react';

export function AdminHeader({ title, subtitle, onSync, isSyncing }) {
    return (
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white/50 pb-6 border-b border-gray-100">
            <div className="flex items-center gap-5">
                <div className="w-14 h-14 bg-gradient-to-tr from-primary to-purple-400 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-500/20">
                    <Zap size={28} fill="currentColor" />
                </div>
                <div>
                    <h1 className="text-3xl font-black text-charcoal tracking-tight">{title}</h1>
                    <p className="text-gray-500 font-medium mt-1">{subtitle}</p>
                </div>
            </div>
            <button
                onClick={onSync}
                disabled={isSyncing}
                className="flex items-center justify-center gap-2 px-6 py-3 bg-white border-2 border-primary rounded-full text-sm font-bold text-primary hover:bg-primary hover:text-white transition-all shadow-sm disabled:opacity-50"
            >
                <RefreshCcw size={18} className={isSyncing ? 'animate-spin' : ''} />
                Sync Data
            </button>
        </div>
    );
}
