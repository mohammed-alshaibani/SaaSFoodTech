'use client';

import { useI18n } from '@/context/I18nContext';
import { Globe } from 'lucide-react';

export default function LanguageToggle() {
  const { locale, changeLanguage, isRTL } = useI18n();

  const toggleLanguage = () => {
    changeLanguage(locale === 'ar' ? 'en' : 'ar');
  };

  return (
    <button
      onClick={toggleLanguage}
      className={`flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors ${isRTL ? 'flex-row-reverse' : ''}`}
      aria-label={`Switch to ${locale === 'ar' ? 'English' : 'Arabic'}`}
    >
      <Globe className="w-4 h-4" />
      <span>{locale === 'ar' ? 'EN' : 'AR'}</span>
    </button>
  );
}
