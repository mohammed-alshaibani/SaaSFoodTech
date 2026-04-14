'use client';

import { useI18n } from '@/context/I18nContext';
import RegisterForm from '@/components/auth/RegisterForm';
import LanguageToggle from '@/components/auth/LanguageToggle';

export default function RegisterPage() {
  const { t, isRTL } = useI18n();

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-100 to-blue-50">
      <div className="bg-white p-8 rounded-2xl shadow-lg w-full max-w-md">
        {/* Language Toggle */}
        <div className={`flex justify-end mb-4 ${isRTL ? 'justify-start' : ''}`}>
          <LanguageToggle />
        </div>

        {/* Header */}
        <div className={`text-center mb-8 ${isRTL ? 'text-right' : 'text-left'}`}>
          <span className="text-3xl">{'\ud83d\udd27'}</span>
          <h1 className="text-2xl font-bold mt-2 text-gray-900">
            {t('auth.register').charAt(0).toUpperCase() + t('auth.register').slice(1)}
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            {isRTL ? '\u0627\u0646\u0636\u0645 \u0625\u0644\u0649 \u0633\u0648\u0642 ServiceHub' : 'Join the ServiceHub marketplace'}
          </p>
        </div>

        {/* Register Form */}
        <RegisterForm />
      </div>
    </div>
  );
}
