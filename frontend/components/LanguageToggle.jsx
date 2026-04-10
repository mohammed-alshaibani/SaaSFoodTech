'use client';

import { useI18n } from '@/context/I18nContext';
import { Globe } from 'lucide-react';

export default function LanguageToggle() {
    const { locale, changeLanguage } = useI18n();

    return (
        <button
            onClick={() => changeLanguage(locale === 'en' ? 'ar' : 'en')}
            className="w-full flex items-center justify-center gap-2 px-3 py-2 bg-gray-50 hover:bg-[#7C3AED]/10 text-gray-600 hover:text-[#7C3AED] rounded-lg text-sm font-medium transition-all"
        >
            <Globe size={16} />
            <span>{locale === 'en' ? 'العربية' : 'English'}</span>
        </button>
    );
}
