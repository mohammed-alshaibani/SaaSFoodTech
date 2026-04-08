'use client';

import { useI18n } from '@/context/I18nContext';

const PARTNERS = [
    "FoodOps", "KitchenPro", "MarketPulse", "FreshConnect", "RapidServe"
];

export default function TrustBar() {
    const { t } = useI18n();

    return (
        <section className="py-12 bg-white/50 backdrop-blur-sm border-y border-gray-100 overflow-hidden">
            <div className="max-w-7xl mx-auto px-4 text-center">
                <p className="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-8 underline decoration-gray-100 underline-offset-8">
                    {t('trust.trustedBy') || 'Trusted by Innovative Service Giants'}
                </p>
                <div className="flex flex-wrap justify-center items-center gap-12 lg:gap-24 grayscale opacity-40">
                    {PARTNERS.map(p => (
                        <span key={p} className="text-xl lg:text-3xl font-black text-gray-900 tracking-tighter hover:grayscale-0 transition-all duration-500 hover:scale-105 select-none cursor-default">
                            {p}
                        </span>
                    ))}
                </div>
            </div>
        </section>
    );
}
