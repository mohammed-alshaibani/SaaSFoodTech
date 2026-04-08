'use client';

import Link from 'next/link';
import { useI18n } from '@/context/I18nContext';
import { useAuth } from '@/context/AuthContext';
import { ArrowRight, LayoutDashboard } from 'lucide-react';

export default function Hero() {
    const { t } = useI18n();
    const { user, loading } = useAuth();

    // Helper to get dashboard path from role
    const getDashboardPath = () => {
        if (!user) return '/register';
        const role = user.roles?.[0] || '';
        if (role === 'admin') return '/dashboard/admin';
        if (role === 'customer') return '/dashboard/customer';
        if (role === 'provider_admin' || role === 'provider_employee') return '/dashboard/provider';
        return '/dashboard/customer';
    };

    return (
        <section className="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden bg-slate-50">
            {/* Background Decor */}
            <div className="absolute top-0 right-0 w-[800px] h-[800px] bg-blue-600/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 -z-10" />
            <div className="absolute bottom-0 left-0 w-[600px] h-[600px] bg-blue-500/5 rounded-full blur-3xl translate-y-1/2 -translate-x-1/3 -z-10" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-4xl mx-auto space-y-8">
                    <div className="flex flex-col items-center gap-6">
                        {/* Badge */}
                        <div className="px-4 py-1.5 rounded-full bg-blue-600/10 border border-blue-600/20 flex items-center gap-2 backdrop-blur-sm shadow-sm">
                            <div className="w-1.5 h-1.5 rounded-full bg-blue-600 animate-pulse" />
                            <span className="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-700">
                                {t('hero.badge')}
                            </span>
                        </div>

                        {/* Headline */}
                        <h1 className="text-5xl md:text-7xl font-bold tracking-tight text-gray-900 leading-[1.1] text-wrap">
                            {t('hero.title')} <span className="text-blue-600 italic">{t('hero.providers')}</span> & <span className="text-blue-500 underline decoration-4 decoration-blue-500/20 underline-offset-8">{t('hero.customers')}</span> {t('hero.through')}
                        </h1>

                        {/* Subtext */}
                        <p className="text-lg md:text-xl text-gray-600 max-w-2xl font-medium leading-relaxed">
                            {t('hero.description')}
                        </p>

                        {/* CTAs */}
                        <div className="flex flex-wrap justify-center gap-4 pt-4">
                            {!loading && user ? (
                                <Link
                                    href={getDashboardPath()}
                                    className="px-8 py-4 rounded-full bg-blue-600 text-white text-base font-bold hover:bg-blue-700 transition-all active:scale-[0.98] shadow-2xl shadow-blue-600/20 flex items-center gap-2"
                                >
                                    <LayoutDashboard className="w-5 h-5" />
                                    {t('common.dashboard')}
                                </Link>
                            ) : (
                                <Link
                                    href="/register"
                                    className="px-8 py-4 rounded-full bg-blue-600 text-white text-base font-bold hover:bg-blue-700 transition-all active:scale-[0.98] shadow-2xl shadow-blue-600/20 flex items-center gap-2"
                                >
                                    {t('hero.getStarted')}
                                    <ArrowRight className="w-5 h-5" />
                                </Link>
                            )}
                            <Link
                                href="#features"
                                className="px-8 py-4 rounded-full bg-white border border-gray-200 text-gray-900 text-base font-bold hover:border-blue-600/30 transition-all active:scale-[0.98] shadow-sm flex items-center gap-2"
                            >
                                {t('hero.exploreFeatures')}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
