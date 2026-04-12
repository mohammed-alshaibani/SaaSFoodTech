'use client';

import React from 'react';
import { Clock, MapPin } from 'lucide-react';
import { format } from 'date-fns';

export function CustomerRequestRow({ req, t }) {
    return (
        <div className="p-6 hover:bg-gray-50/50 transition-all cursor-pointer group">
            <div className="flex flex-col md:flex-row justify-between gap-6">
                <div className="space-y-3 flex-1">
                    <div className="flex items-center gap-3">
                        <h3 className="text-lg font-bold text-charcoal group-hover:text-primary transition-colors">{req.title}</h3>
                        <span className={`px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider ${req.status === 'pending' ? 'bg-amber-100 text-amber-600 border border-amber-200' : req.status === 'completed' ? 'bg-emerald-100 text-emerald-600 border border-emerald-200' : 'bg-primary/10 text-primary border border-primary/20'}`}>
                            {t(`dashboard.${req.status}`) || req.status}
                        </span>
                    </div>
                    <p className="text-gray-500 font-medium line-clamp-2 max-w-3xl leading-relaxed text-sm">{req.description}</p>
                    <div className="flex flex-wrap gap-3 pt-1">
                        <div className="flex items-center gap-2 px-3 py-1.5 bg-gray-100 rounded-lg border border-gray-200">
                            <Clock size={14} className="text-primary" />
                            <span className="text-[11px] font-bold text-gray-500">
                                {req.created_at ? format(new Date(req.created_at), 'yyyy/MM/dd') : 'N/A'}
                            </span>
                        </div>
                        <div className="flex items-center gap-2 px-3 py-1.5 bg-gray-100 rounded-lg border border-gray-200">
                            <MapPin size={14} className="text-primary" />
                            <span className="text-[11px] font-bold text-gray-500">{req.latitude?.toFixed(2)}, {req.longitude?.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
                {req.provider && (
                    <div className="flex items-center gap-3 bg-primary/5 p-3 rounded-2xl border border-primary/10 h-fit">
                        <div className="w-10 h-10 bg-primary rounded-xl flex items-center justify-center text-white font-bold shadow-md shadow-purple-500/20 uppercase">
                            {req.provider.name?.[0] || 'P'}
                        </div>
                        <div>
                            <p className="text-[10px] font-bold text-primary/70 uppercase tracking-wider">{t('dashboard.provider') || 'Provider'}</p>
                            <p className="text-sm font-bold text-charcoal">{req.provider.name}</p>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
