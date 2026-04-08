'use client';

import { useI18n } from '@/context/I18nContext';
import {
    ShieldCheck,
    Search,
    MapPin,
    CheckCircle2,
    Clock,
    TrendingUp,
    User,
    PlusCircle,
    Zap
} from 'lucide-react';

export default function DashboardMock() {
    const { t } = useI18n();

    return (
        <section className="py-20 lg:py-32 bg-slate-50 relative overflow-hidden">
            {/* Decorative Grid */}
            <div className="absolute inset-0 opacity-[0.03] -z-10 [background-image:linear-gradient(to_right,#000_1px,transparent_1px),linear-gradient(to_bottom,#000_1px,transparent_1px)] [background-size:40px_40px]" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header content */}
                <div className="text-center mb-16 space-y-4">
                    <div className="text-blue-600 font-bold tracking-widest text-xs uppercase">
                        {t('dashboard.ecosystem') || 'Digital Ecosystem'}
                    </div>
                    <h2 className="text-3xl md:text-5xl font-bold text-gray-900 leading-tight">
                        {t('dashboard.title') || 'A Unified Platform For Every'} <span className="text-blue-500">{t('dashboard.persona') || 'Persona'}</span>.
                    </h2>
                    <p className="text-gray-500 max-w-xl mx-auto font-medium">
                        {t('dashboard.description') || 'From the Admin command center to the Provider mobile experience ServiceHub synchronizes your business lifecycle in real-time.'}
                    </p>
                </div>

                {/* The Dashboard Card */}
                <div className="relative group lg:p-4">
                    {/* Glass Card Outer */}
                    <div className="bg-white/40 backdrop-blur-2xl rounded-[2.5rem] border border-white p-4 md:p-8 shadow-2xl shadow-blue-600/5">

                        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
                            {/* Left Sidebar Mock (Nav) */}
                            <div className="hidden lg:col-span-3 lg:flex flex-col gap-10 p-6 bg-white/60 rounded-3xl border border-white shadow-sm font-medium">
                                <div className="space-y-6">
                                    <div className="flex items-center gap-3 text-blue-600">
                                        <TrendingUp size={18} />
                                        <span className="text-sm font-bold">{t('dashboard.insights') || 'Insights'}</span>
                                    </div>
                                    <div className="flex items-center gap-3 text-gray-400 opacity-60">
                                        <Search size={18} />
                                        <span className="text-sm">{t('dashboard.requests') || 'Requests'}</span>
                                    </div>
                                    <div className="flex items-center gap-3 text-gray-400 opacity-60">
                                        <User size={18} />
                                        <span className="text-sm">{t('dashboard.team') || 'Team'}</span>
                                    </div>
                                </div>

                                <div className="mt-auto p-4 bg-blue-600 rounded-2xl text-white space-y-3">
                                    <p className="text-[10px] font-bold uppercase tracking-widest opacity-80 underline decoration-white/20 underline-offset-4">
                                        {t('dashboard.activePlan') || 'Active Plan'}
                                    </p>
                                    <p className="text-lg font-black tracking-tight">{t('dashboard.enterprise') || 'Enterprise'}</p>
                                    <div className="h-1.5 w-full bg-white/20 rounded-full overflow-hidden">
                                        <div className="h-full w-2/3 bg-blue-400 animate-pulse" />
                                    </div>
                                </div>
                            </div>

                            {/* Main Workspace Mock */}
                            <div className="lg:col-span-9 space-y-10">
                                {/* Request Lifecycle Visualization */}
                                <div className="bg-white/90 p-8 rounded-[2rem] border border-white shadow-xl shadow-slate-200/50 space-y-10 relative overflow-hidden">
                                    <div className="flex justify-between items-center">
                                        <h4 className="font-bold text-gray-900 flex items-center gap-2">
                                            <Zap className="text-blue-500 fill-blue-500" size={18} />
                                            {t('dashboard.liveTracking') || 'Live Lifecycle Tracking'}
                                        </h4>
                                        <span className="text-[10px] font-black text-emerald-600 bg-emerald-50 px-2 py-1 rounded-md border border-emerald-100 uppercase tracking-widest">
                                            {t('dashboard.realtime') || 'Real-time sync'}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between gap-2 md:px-10 relative">
                                        {/* Progress line */}
                                        <div className="absolute top-6 left-16 right-16 h-1 bg-gray-100 -z-10 rounded-full overflow-hidden">
                                            <div className="h-full w-1/2 bg-blue-600" />
                                        </div>

                                        <LifecycleItem label={t('dashboard.pending') || 'Pending'} active={true} icon={Clock} />
                                        <LifecycleItem label={t('dashboard.accepted') || 'Accepted'} active={true} icon={ShieldCheck} />
                                        <LifecycleItem label={t('dashboard.complete') || 'Complete'} active={false} icon={CheckCircle2} />
                                    </div>
                                </div>

                                {/* Grid items */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {/* Nearby Radar Mock */}
                                    <div className="bg-blue-800/95 p-6 rounded-[2rem] text-white flex flex-col justify-between aspect-video md:aspect-auto">
                                        <div className="flex justify-between items-start">
                                            <div className="p-3 bg-white/10 rounded-2xl backdrop-blur-md">
                                                <MapPin size={24} className="text-blue-400" />
                                            </div>
                                            <div className="text-right">
                                                <p className="text-xs opacity-60 font-medium">{t('dashboard.nearbyRadius') || 'Nearby Radius'}</p>
                                                <p className="text-2xl font-bold">50km</p>
                                            </div>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium opacity-80 mb-2">{t('dashboard.geoEngine') || 'Geolocation Engine'}</p>
                                            <div className="h-1 bg-white/10 rounded-full">
                                                <div className="h-full w-4/5 bg-blue-400 rounded-full" />
                                            </div>
                                        </div>
                                    </div>

                                    {/* API Integration Mock */}
                                    <div className="bg-white p-6 rounded-[2rem] border border-gray-100 shadow-xl shadow-slate-200/50 flex flex-col justify-between">
                                        <div className="flex items-center gap-4">
                                            <div className="p-3 bg-blue-500 text-white rounded-2xl shadow-lg shadow-blue-500/20">
                                                <PlusCircle size={24} />
                                            </div>
                                            <div>
                                                <p className="font-bold text-gray-900 leading-none mb-1">{t('dashboard.scaleLimit') || 'Scale Limit'}</p>
                                                <p className="text-xs text-gray-400 font-medium tracking-tight">{t('dashboard.unlimited') || 'Unlimited Requests'}</p>
                                            </div>
                                        </div>
                                        <div className="pt-4 mt-4 border-t border-gray-50 flex justify-between items-center text-xs font-bold uppercase tracking-widest text-blue-600 opacity-60">
                                            <span>{t('dashboard.paidTier') || 'Paid Tier Active'}</span>
                                            <CheckCircle2 size={16} />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}

// Helper component
function LifecycleItem({ status, active, label, icon: Icon }) {
    return (
        <div className={`flex flex-col items-center gap-3 transition-opacity duration-500 ${
            active ? "opacity-100 scale-110" : "opacity-30"
        }`}>
            <div className={`w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg transition-colors border-2 ${
                active ? "bg-blue-600 border-blue-600 text-white scale-110" : "bg-white border-gray-100 text-gray-400"
            }`}>
                <Icon size={22} strokeWidth={2.5} />
            </div>
            <span className={`text-[10px] uppercase font-black tracking-widest transition-colors ${
                active ? "text-blue-500" : "text-gray-400"
            }`}>
                {label}
            </span>
        </div>
    );
}
