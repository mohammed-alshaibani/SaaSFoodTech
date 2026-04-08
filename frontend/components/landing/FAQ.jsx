'use client';

import { useState } from 'react';
import { ChevronDown, MessageCircleQuestion } from 'lucide-react';
import { useI18n } from '@/context/I18nContext';

export default function FAQ() {
    const { t } = useI18n();

    const FAQS = [
        {
            q: t('faq.q1') || "How does the nearby filtering work?",
            a: t('faq.a1') || "Our engine uses highly optimized spatial queries (MySQL ST_Distance_Sphere) to retrieve service requests within a 50km radius of your current location. We also provide a trigonometric fallback for local development on SQLite."
        },
        {
            q: t('faq.q2') || "Is the AI description enhancement mandatory?",
            a: t('faq.a2') || "Not at all. Customers can choose to 'Enhance' their rough descriptions via our integrated AI service, but original text is preserved if the service is bypassed or fails."
        },
        {
            q: t('faq.q3') || "Can I manage multiple provider accounts?",
            a: t('faq.a3') || "Yes. Our Advanced RBAC system supports 'Provider Admin' roles that can manage permissions for multiple 'Provider Employee' accounts within the same organization."
        },
        {
            q: t('faq.q4') || "How is session security handled?",
            a: t('faq.a4') || "We prioritize security over convenience by using httpOnly, secure, SameSite=Lax cookies for session management. This effectively mitigates common XSS and CSRF attack vectors."
        },
    ];

    function FAQItem({ q, a }) {
        const [isOpen, setIsOpen] = useState(false);

        return (
            <div className={`overflow-hidden rounded-2xl border transition-all ${
                isOpen ? 'border-blue-600 bg-blue-600/5 bg-white/40 backdrop-blur-md' : 'border-gray-100 bg-white hover:border-gray-200'
            }`}>
                <button
                    onClick={() => setIsOpen(!isOpen)}
                    className="w-full flex items-center justify-between p-6 text-left"
                >
                    <span className="font-bold text-gray-900 pr-4">{q}</span>
                    <ChevronDown className={`text-blue-600 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
                </button>
                {isOpen && (
                    <div className="px-6 pb-6 text-sm text-gray-600 leading-relaxed font-medium">
                        {a}
                    </div>
                )}
            </div>
        );
    }

    return (
        <section id="faq" className="py-24 bg-white relative">
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col items-center gap-4 mb-16 text-center">
                    <div className="w-16 h-16 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mb-2 shadow-sm border border-blue-100/50">
                        <MessageCircleQuestion size={28} />
                    </div>
                    <h2 className="text-3xl md:text-5xl font-bold text-gray-900 tracking-tight">
                        {t('faq.title') || 'Common'} <span className="text-blue-600 italic font-serif">{t('faq.inquiries') || 'Inquiries'}</span>.
                    </h2>
                    <p className="text-gray-500 font-medium">{t('faq.description') || 'Everything you need to know about the marketplace architecture.'}</p>
                </div>
                <div className="space-y-4">
                    {FAQS.map(faq => <FAQItem key={faq.q} {...faq} />)}
                </div>
            </div>
        </section>
    );
}
