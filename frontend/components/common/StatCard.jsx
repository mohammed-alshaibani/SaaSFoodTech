'use client';

import React from 'react';

/**
 * Standardized StatCard
 * Used across Admin, Provider, and Customer dashboards.
 */
export function StatCard({ title, subtitle, value, icon: Icon, color = 'purple' }) {
    const colorStyles = {
        purple: 'text-primary bg-primary/10 border-primary/20 from-primary/20',
        blue: 'text-blue-500 bg-blue-50 border-blue-200 from-blue-500/20',
        emerald: 'text-emerald-500 bg-emerald-50 border-emerald-200 from-emerald-500/20',
        amber: 'text-amber-500 bg-amber-50 border-amber-200 from-amber-500/20',
    };

    const style = colorStyles[color] || colorStyles.purple;

    return (
        <div className="bg-white p-6 rounded-3xl border border-gray-100 shadow-[0_4px_20px_rgb(0,0,0,0.03)] relative overflow-hidden group hover:shadow-[0_8px_30px_rgb(124,58,237,0.08)] transition-all duration-300">
            {/* Background Blob */}
            <div className={`absolute -right-6 -top-6 w-32 h-32 bg-gradient-to-br ${style} to-transparent rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700`} />

            <div className="flex justify-between items-start mb-6">
                <div className="z-10">
                    <h3 className="text-xl font-black text-charcoal truncate max-w-[140px]">{title}</h3>
                    {subtitle && <p className="text-[10px] font-bold text-gray-400 mt-1 uppercase tracking-widest">{subtitle}</p>}
                </div>
                <div className={`p-3 rounded-2xl z-10 transition-transform group-hover:scale-110 border ${style}`}>
                    <Icon size={24} strokeWidth={2.5} />
                </div>
            </div>

            <p className="text-4xl font-black text-charcoal mt-2 relative z-10">{value}</p>
        </div>
    );
}

export function StatSkeleton() {
    return (
        <div className="h-44 bg-gray-50 rounded-3xl animate-pulse border border-gray-100" />
    );
}
