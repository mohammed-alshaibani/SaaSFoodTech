'use client';

import Link from 'next/link';
import { useI18n } from '@/context/I18nContext';

export default function Footer() {
    const { t } = useI18n();

    return (
        <footer className="py-20 bg-blue-900 text-white relative overflow-hidden">
            {/* Background Decor */}
            <div className="absolute top-0 left-0 w-full h-[1px] bg-white/10" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Main Footer Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-24 items-start mb-20">
                    {/* Logo & About */}
                    <div className="space-y-6">
                        <Link href="/" className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-blue-900 shadow-xl shadow-white/10">
                                <span className="text-lg font-bold">🚀</span>
                            </div>
                            <span className="text-xl font-bold tracking-tight">ServiceHub</span>
                        </Link>
                        <p className="text-sm font-medium text-white/50 leading-relaxed">
                            {t('footer.about') || 'The next-generation service marketplace platform for enterprises. Modernizing the way providers and customers interact.'}
                        </p>
                        <div className="flex items-center gap-4 pt-4">
                            <span className="text-white/40 hover:text-blue-400 transition-colors cursor-pointer text-lg">𝕏</span>
                            <span className="text-white/40 hover:text-blue-400 transition-colors cursor-pointer text-lg">in</span>
                            <span className="text-white/40 hover:text-blue-400 transition-colors cursor-pointer text-lg">⚡</span>
                        </div>
                    </div>

                    {/* Solution Links */}
                    <div className="space-y-6">
                        <h4 className="text-xs font-black uppercase tracking-widest text-blue-400">{t('footer.solutions') || 'Solutions'}</h4>
                        <div className="flex flex-col gap-4 text-sm font-medium text-white/60">
                            <Link href="#features" className="hover:text-white transition-colors underline decoration-white/10 underline-offset-4 decoration-dashed">{t('footer.features') || 'Feature Grid'}</Link>
                            <Link href="#pricing" className="hover:text-white transition-colors underline decoration-white/10 underline-offset-4 decoration-dashed">{t('footer.pricing') || 'Subscription Tiers'}</Link>
                            <Link href="#" className="hover:text-white transition-colors underline decoration-white/10 underline-offset-4 decoration-dashed">{t('footer.api') || 'API Sandbox'}</Link>
                        </div>
                    </div>

                    {/* Resources Links */}
                    <div className="space-y-6">
                        <h4 className="text-xs font-black uppercase tracking-widest text-blue-400">{t('footer.knowledge') || 'Knowledge'}</h4>
                        <div className="flex flex-col gap-4 text-sm font-medium text-white/60">
                            <Link href="/README.md" className="hover:text-white transition-colors underline decoration-white/10 underline-offset-4 decoration-dashed">{t('footer.architecture') || 'Architecture'}</Link>
                            <Link href="/API_DOCS.md" className="hover:text-white transition-colors underline decoration-white/10 underline-offset-4 decoration-dashed">{t('footer.api') || 'API Reference'}</Link>
                            <Link href="#" className="hover:text-white transition-colors underline decoration-white/10 underline-offset-4 decoration-dashed">{t('footer.docs') || 'Documentation'}</Link>
                        </div>
                    </div>

                    {/* CTA Box */}
                    <div className="p-8 bg-white/5 rounded-3xl border border-white/5 space-y-6">
                        <h4 className="text-sm font-black tracking-tight leading-tight">{t('footer.ctaTitle') || 'Ready to transform your delivery?'}</h4>
                        <Link
                            href="/register"
                            className="block w-full py-4 text-center rounded-2xl bg-blue-500 text-white text-xs font-black uppercase tracking-widest hover:bg-blue-600 transition-all active:scale-[0.98] shadow-2xl shadow-blue-500/20"
                        >
                            {t('footer.getStarted') || 'Get Started'}
                        </Link>
                    </div>
                </div>

                {/* Bottom Bar */}
                <div className="pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-4 text-[10px] font-bold text-white/40 uppercase tracking-widest">
                    <p>{t('footer.copyright') || '© 2026 SERVICEHUB MARKETPLACE. ALL RIGHTS RESERVED.'}</p>
                    <div className="flex gap-8">
                        <span className="hover:text-white transition-colors cursor-pointer">{t('footer.privacy') || 'Privacy Protocol'}</span>
                        <span className="hover:text-white transition-colors cursor-pointer">{t('footer.security') || 'Security Compliance'}</span>
                    </div>
                </div>
            </div>
        </footer>
    );
}
