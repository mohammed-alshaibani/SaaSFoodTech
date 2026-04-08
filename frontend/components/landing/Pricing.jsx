'use client';

import { CheckCircle2, XCircle } from 'lucide-react';
import Link from 'next/link';
import { useI18n } from '@/context/I18nContext';

export default function Pricing() {
    const { t } = useI18n();

    const TIERS = [
        {
            name: t('pricing.freeTier') || "Free Tier",
            price: "0",
            desc: t('pricing.freeDesc') || "Ideal for individuals and small service explorations.",
            features: [
                { name: t('pricing.feature1') || "Up to 3 Service Requests", active: true },
                { name: t('pricing.feature2') || "Nearby Filtering (50km)", active: true },
                { name: t('pricing.feature3') || "AI Description Enhancement", active: true },
                { name: t('pricing.feature4') || "Advanced RBAC Management", active: false },
                { name: t('pricing.feature5') || "Priority Provider Support", active: false },
                { name: t('pricing.feature6') || "Unlimited Scaling", active: false },
            ],
            cta: t('pricing.getStarted') || "Get Started Free",
            popular: false,
        },
        {
            name: t('pricing.proTier') || "Pro Tier",
            price: "29",
            desc: t('pricing.proDesc') || "The professional choice for scaling service businesses.",
            features: [
                { name: t('pricing.feature7') || "Unlimited Service Requests", active: true },
                { name: t('pricing.feature2') || "Nearby Filtering (50km)", active: true },
                { name: t('pricing.feature3') || "AI Description Enhancement", active: true },
                { name: t('pricing.feature4') || "Advanced RBAC Management", active: true },
                { name: t('pricing.feature5') || "Priority Provider Support", active: true },
                { name: t('pricing.feature8') || "Platform Stats & Insights", active: true },
            ],
            cta: t('pricing.upgrade') || "Upgrade to Pro",
            popular: true,
        },
    ];

    return (
        <section id="pricing" className="py-24 bg-slate-50 relative overflow-hidden">
            {/* Background Decor */}
            <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-blue-500/5 rounded-full blur-[100px] -z-10" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="text-center mb-16 space-y-4">
                    <h2 className="text-3xl md:text-5xl font-bold text-gray-900 tracking-tight">
                        {t('pricing.title') || 'Transparent Scaling'} <span className="text-blue-500 underline decoration-blue-500/10 underline-offset-4 decoration-8">{t('pricing.strategy') || 'Strategy'}</span>.
                    </h2>
                    <p className="text-gray-500 font-medium max-w-xl mx-auto leading-relaxed">
                        {t('pricing.description') || 'No complicated contracts. Choose a plan that suits your current marketplace volume and upgrade as you grow.'}
                    </p>
                </div>

                {/* Pricing Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
                    {TIERS.map((tier, idx) => (
                        <div
                            key={tier.name}
                            className={`p-10 rounded-[2.5rem] border bg-white relative shadow-xl shadow-slate-200/50 flex flex-col h-full transform transition-all hover:scale-[1.02] ${
                                tier.popular ? 'border-blue-600 ring-2 ring-blue-600/5' : 'border-gray-100'
                            }`}
                        >
                            {tier.popular && (
                                <div className="absolute -top-4 left-10 py-1.5 px-4 bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest rounded-full shadow-lg shadow-blue-600/20">
                                    {t('pricing.mostPopular') || 'Most Popular'}
                                </div>
                            )}

                            <div className="mb-10">
                                <h3 className="text-2xl font-black text-gray-900 mb-2">{tier.name}</h3>
                                <p className="text-sm text-gray-400 font-bold mb-8 uppercase tracking-widest leading-none">{t('pricing.startingAt') || 'Starting at'}</p>
                                <div className="flex items-baseline gap-1">
                                    <span className="text-5xl font-black text-gray-900 leading-none">${tier.price}</span>
                                    <span className="text-gray-400 font-medium tracking-tight">/mo</span>
                                </div>
                                <p className="text-sm text-gray-500 font-medium mt-6 leading-relaxed">
                                    {tier.desc}
                                </p>
                            </div>

                            <div className="space-y-4 mb-12 flex-grow">
                                {tier.features.map(f => (
                                    <div key={f.name} className="flex items-center gap-3">
                                        <div className={f.active ? 'text-blue-600' : 'text-slate-200'}>
                                            {f.active ? <CheckCircle2 size={18} strokeWidth={3} /> : <XCircle size={18} strokeWidth={2} />}
                                        </div>
                                        <span className={`text-[13px] font-bold tracking-tight ${f.active ? 'text-gray-700' : 'text-slate-300'}`}>
                                            {f.name}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            <Link
                                href="/register"
                                className={`w-full py-4 rounded-2xl text-sm font-black transition-all shadow-lg active:scale-[0.98] text-center ${
                                    tier.popular
                                        ? 'bg-blue-600 text-white hover:bg-blue-700 shadow-blue-600/10'
                                        : 'bg-slate-100 text-gray-800 hover:bg-slate-200'
                                }`}
                            >
                                {tier.cta}
                            </Link>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
