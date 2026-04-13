'use client';

import { useI18n } from '@/context/I18nContext';
import LoginForm from '@/components/auth/LoginForm';
import LanguageToggle from '@/components/auth/LanguageToggle';
import Link from 'next/link';

export default function LoginPage() {
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
            ServiceHub
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            {t('auth.login').charAt(0).toUpperCase() + t('auth.login').slice(1)} {t('auth.login') === 'Login' ? 'to your account' : '\u0625\u0644\u0649 \u062d\u0633\u0627\u0628\u0643'}
          </p>
        </div>

        {/* Login Form */}
        <LoginForm />

        {/* Register Link */}
        <div className="mt-6 text-center">
          <p className="text-sm text-gray-600">
            {t('auth.dontHaveAccount')}{' '}
            <Link
              href="/register"
              className="text-blue-600 hover:text-blue-500 font-medium"
            >
              {t('auth.signUp')}
            </Link>
          </p>
        </div>

        {/* Test Credentials */}
        <details className="mt-6 text-xs text-gray-400">
          <summary className="cursor-pointer hover:text-gray-600">
            {isRTL ? '\u0627\u0644\u0628\u064a\u0627\u0646\u0627\u062a \u0627\u0644\u062a\u062c\u0631\u064a\u0628\u064a\u0629' : 'Test credentials'}
          </summary>
          <div className="mt-2 space-y-1 font-mono text-center">
            <div>admin@test.com / password</div>
            <div>provider@test.com / password</div>
            <div>customer@test.com / password</div>
          </div>
        </details>
      </div>
    </div>
  );
}
