'use client';

import React from 'react';
import { MapPin, Calendar, CheckCircle2 } from 'lucide-react';
import { format } from 'date-fns';

const StatusBadge = ({ status, t }) => {
    const config = {
        pending: 'bg-amber-50 text-amber-600 border-amber-200',
        accepted: 'bg-primary/10 text-primary border-primary/20',
        completed: 'bg-emerald-50 text-emerald-600 border-emerald-200',
    };
    return (
        <span className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border ${config[status] || 'bg-gray-100 text-gray-500 border-gray-200'}`}>
            {t(`dashboard.${status}`) || status}
        </span>
    );
};

export function RequestCard({ req, user, t, onAccept, onComplete, isAccepting, isCompleting }) {
    return (
        <div className="bg-white border border-gray-200 p-6 rounded-3xl shadow-[0_4px_20px_rgb(0,0,0,0.03)] hover:shadow-[0_8px_30px_rgb(124,58,237,0.08)] hover:border-primary/30 transition-all flex flex-col h-full group">
            <div className="flex justify-between items-start gap-4 mb-4">
                <div className="flex-1 min-w-0">
                    <h3 className="text-lg font-black text-charcoal group-hover:text-primary transition-colors truncate">{req.title}</h3>
                    <div className="mt-2">
                        <StatusBadge status={req.status} t={t} />
                    </div>
                </div>
                {req.distance_km != null && (
                    <div className="bg-primary/10 border border-primary/20 px-3 py-1.5 rounded-lg flex items-center gap-1.5 whitespace-nowrap">
                        <MapPin size={12} className="text-primary" />
                        <span className="text-[10px] font-bold text-primary">{req.distance_km.toFixed(1)} KM</span>
                    </div>
                )}
            </div>

            <p className="text-gray-500 font-medium text-sm leading-relaxed mb-6 flex-1 line-clamp-4">{req.description}</p>

            <div className="space-y-3 mb-6">
                <div className="flex items-center gap-3 text-gray-500">
                    <Calendar size={14} className="text-primary" />
                    <span className="text-[10px] font-bold uppercase tracking-wider">{req.created_at ? format(new Date(req.created_at), 'yyyy/MM/dd') : 'N/A'}</span>
                </div>
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center text-gray-600 font-black">
                        {req.customer?.name?.[0]?.toUpperCase()}
                    </div>
                    <div>
                        <p className="text-[8px] font-bold text-gray-400 uppercase tracking-wider">{t('dashboard.customer')}</p>
                        <p className="text-xs font-bold text-charcoal">{req.customer?.name ?? 'Unknown'}</p>
                    </div>
                </div>
            </div>

            <div className="pt-4 border-t border-gray-100">
                {req.status === 'pending' && (
                    <button
                        onClick={() => onAccept(req.id)}
                        disabled={isAccepting}
                        className="w-full py-3 bg-primary hover:bg-purple-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-md shadow-purple-500/20 transition-all active:scale-[0.98] disabled:opacity-50"
                    >
                        {isAccepting ? '...PROCESSING' : t('dashboard.acceptRequest') || 'Accept Request'}
                    </button>
                )}
                {req.status === 'accepted' && req.provider?.id === user?.id && (
                    <button
                        onClick={() => onComplete(req.id)}
                        disabled={isCompleting}
                        className="w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-md shadow-emerald-500/20 transition-all active:scale-[0.98] disabled:opacity-50"
                    >
                        {isCompleting ? '...FINISHING' : t('dashboard.markCompleted') || 'Mark Completed'}
                    </button>
                )}
                {req.status === 'accepted' && req.provider?.id !== user?.id && (
                    <div className="w-full text-center py-3 bg-gray-100 rounded-xl text-xs font-bold text-gray-500 uppercase tracking-wider border border-gray-200 italic">
                        {t('dashboard.acceptedByOther') || 'Accepted by another provider'}
                    </div>
                )}
                {req.status === 'completed' && (
                    <div className="w-full flex items-center justify-center gap-3 py-3 bg-emerald-50 rounded-xl text-xs font-bold text-emerald-600 uppercase tracking-wider border border-emerald-200">
                        <CheckCircle2 size={16} /> Order Completed
                    </div>
                )}
            </div>
        </div>
    );
}
