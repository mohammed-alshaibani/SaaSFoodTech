'use client';

import { useI18n } from '@/context/I18nContext';
import {
    ShieldCheck,
    MapPin,
    Zap,
    Smartphone,
    BarChart3,
    Lock
} from 'lucide-react';

export default function Features() {
    const { t } = useI18n();

    const FEATURE_LIST = [
        {
            title: t('features.rbac') || "Advanced RBAC",
            description: t('features.rbacDesc') || "Multi-layered role-based access control with dynamic permission syncing. Admin, Provider, and Customer flows are strictly separated at the API level.",
            icon: Lock,
            color: "blue",
        },
        {
            title: t('features.geo') || "Geolocation Engine",
            description: t('features.geoDesc') || "Powerful nearby request filtering with a 50km radius. Supports high-performance spatial queries with full local development fallback.",
            icon: MapPin,
            color: "emerald",
        },
        {
            title: t('features.ai') || "AI Descriptions",
            description: t('features.aiDesc') || "Built-in AI-powered service description enhancement. Let LLMs rewrite rough request drafts into professional service briefs automatically.",
            icon: Zap,
            color: "amber",
        },
        {
            title: t('features.subscription') || "Subscription Gating",
            description: t('features.subscriptionDesc') || "Monetize your marketplace with flexible feature-gating. Enforce creation limits for free users and unlock scale for premium subscribers.",
            icon: BarChart3,
            color: "purple",
        },
        {
            title: t('features.mobile') || "Mobile-Optimized",
            description: t('features.mobileDesc') || "Fully responsive layouts designed for on-the-go service providers. Real-time status updates and simplified acceptance flows.",
            icon: Smartphone,
            color: "red",
        },
        {
            title: t('features.security') || "Security First",
            description: t('features.securityDesc') || "Enterprise-grade session management with httpOnly cookies. Protection against XSS and CSRF out of the box.",
            icon: ShieldCheck,
            color: "indigo",
        },
    ];

    const COLORS = {
        blue: "bg-blue-50 text-blue-600 border-blue-100",
        emerald: "bg-emerald-50 text-emerald-600 border-emerald-100",
        amber: "bg-amber-50 text-amber-600 border-amber-100",
        purple: "bg-purple-50 text-purple-600 border-purple-100",
        red: "bg-red-50 text-red-600 border-red-100",
        indigo: "bg-indigo-50 text-indigo-600 border-indigo-100",
    };

    return (
        <section id="features" className="py-24 bg-white">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="text-center mb-20 space-y-4">
                    <h2 className="text-3xl md:text-5xl font-bold text-gray-900 tracking-tight">
                        {t('features.engineered') || 'Engineered for'} <span className="text-blue-600 underline decoration-blue-600/10 underline-offset-4 decoration-8">{t('features.reliability') || 'Reliability'}</span>.
                    </h2>
                    <p className="text-gray-500 font-medium max-w-2xl mx-auto leading-relaxed">
                        {t('features.description') || 'Our marketplace engine is built with the modern stack to ensure data integrity, speed, and cross-platform flexibility from day one.'}
                    </p>
                </div>

                {/* Feature Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {FEATURE_LIST.map((feat, idx) => (
                        <div
                            key={feat.title}
                            className="p-8 rounded-3xl border border-gray-100 hover:border-blue-600/20 hover:shadow-xl hover:shadow-blue-600/5 transition-all group"
                        >
                            <div className={`w-14 h-14 rounded-2xl flex items-center justify-center mb-6 border transition-transform group-hover:scale-110 ${COLORS[feat.color]}`}>
                                <feat.icon size={24} />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-3">{feat.title}</h3>
                            <p className="text-sm text-gray-500 leading-relaxed font-medium">
                                {feat.description}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
