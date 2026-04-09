'use client';

import { useState } from 'react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { useI18n } from '@/context/I18nContext';

export default function SubscriptionPlans({ plans, currentPlan, onUpgrade }) {
    const [loading, setLoading] = useState(null);
    const { t } = useI18n();

    const handleUpgradeClick = async (planName) => {
        setLoading(planName);
        try {
            await onUpgrade(planName);
        } finally {
            setLoading(null);
        }
    };

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto relative z-10 w-full pb-8">
            {plans.map((plan) => {
                const isCurrentPlan = plan.name === currentPlan;
                const canUpgrade = !isCurrentPlan && plan.name !== 'free';
                const isPopular = plan.name === 'premium' || plan.name === 'pro';

                // Extract features for the modern SaaS aesthetic 
                const features = [
                    { name: `Up to ${plan.limits.requests_per_month === 'unlimited' ? 'Unlimited' : plan.limits.requests_per_month} Service Requests`, active: true },
                    { name: `${plan.limits.attachments_per_request} Attachments per Request`, active: true },
                    { name: "AI Description Enhancement", active: !!plan.features.ai_enhancement },
                    { name: "Priority Provider Support", active: !!plan.features.priority_support },
                    { name: "Advanced API Access", active: !!plan.features.api_access },
                ];

                return (
                    <div
                        key={plan.name}
                        className={`p-10 rounded-[2.5rem] border bg-white relative shadow-xl flex flex-col h-full transform transition-all hover:scale-[1.02] ${isPopular ? 'border-blue-600 ring-2 ring-blue-600/5 shadow-blue-500/10' : 'border-gray-100 shadow-slate-200/50'
                            } ${isCurrentPlan ? 'opacity-95 ring-2 ring-emerald-500/10' : ''}`}
                    >
                        {isPopular && (
                            <div className="absolute -top-4 left-10 py-1.5 px-4 bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest rounded-full shadow-lg shadow-blue-600/20">
                                {t('pricing.mostPopular') || 'Most Popular'}
                            </div>
                        )}
                        {isCurrentPlan && (
                            <div className="absolute -top-4 right-10 py-1.5 px-4 bg-emerald-500 text-white text-[10px] font-black uppercase tracking-widest rounded-full shadow-lg shadow-emerald-500/20">
                                Current Plan
                            </div>
                        )}

                        <div className="mb-10">
                            <h3 className="text-2xl font-black text-gray-900 mb-2">{plan.display_name}</h3>
                            <p className="text-sm text-gray-400 font-bold mb-8 uppercase tracking-widest leading-none">
                                Starting at
                            </p>
                            <div className="flex items-baseline gap-1">
                                <span className="text-5xl font-black text-gray-900 leading-none">
                                    {plan.formatted_price || `$${plan.price || '0'}`}
                                </span>
                                <span className="text-gray-400 font-medium tracking-tight">/mo</span>
                            </div>
                            <p className="text-sm text-gray-500 font-medium mt-6 leading-relaxed">
                                {plan.description}
                            </p>
                        </div>

                        <div className="space-y-4 mb-12 flex-grow">
                            {features.map(f => (
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

                        <button
                            onClick={() => handleUpgradeClick(plan.name)}
                            disabled={!canUpgrade || loading === plan.name}
                            className={`w-full py-4 rounded-2xl text-sm font-black transition-all shadow-lg active:scale-[0.98] text-center ${isCurrentPlan
                                    ? 'bg-slate-100 text-gray-400 cursor-not-allowed shadow-none'
                                    : isPopular
                                        ? 'bg-blue-600 text-white hover:bg-blue-700 shadow-blue-600/10'
                                        : 'bg-slate-100 text-gray-800 hover:bg-slate-200 shadow-slate-200/50'
                                }`}
                        >
                            {loading === plan.name
                                ? 'Processing...'
                                : isCurrentPlan
                                    ? 'Current Plan'
                                    : plan.name === 'free'
                                        ? 'Downgrade'
                                        : 'Upgrade to Pro'}
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
