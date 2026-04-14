'use client';

import { createContext, useContext, useState, useEffect } from 'react';

const i18nConfig = {
  useSuspense: true,
  fallbackLng: 'ar',
};

const I18nContext = createContext(undefined);

export function I18nProvider({ children, initialLocale = 'ar', initialTranslations = {} }) {
  const [locale, setLocale] = useState(initialLocale);
  const [translations, setTranslations] = useState(initialTranslations);
  const [loading, setLoading] = useState(Object.keys(initialTranslations).length === 0);

  useEffect(() => {
    async function loadTranslations() {
      if (Object.keys(translations).length > 0 && locale === initialLocale) {
        setLoading(false);
        return;
      }

      setLoading(true);
      try {
        const response = await fetch(`/locales/${locale}/common.json`);
        const data = await response.json();
        setTranslations(data);
      } catch (error) {
        console.error('Failed to load translations:', error);
      } finally {
        setLoading(false);
      }
    }

    loadTranslations();

    document.cookie = `NEXT_LOCALE=${locale}; path=/; max-age=31536000`;
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = locale;
  }, [locale, initialLocale]);

  const t = (key) => {
    if (loading && i18nConfig.useSuspense) return '';
    if (!translations || Object.keys(translations).length === 0) return key;

    const keys = key.split('.');
    let value = translations;

    for (const k of keys) {
      value = value?.[k];
    }

    return value || key;
  };

  const changeLanguage = (newLocale) => {
    if (newLocale === 'ar' || newLocale === 'en') {
      const currentPath = window.location.pathname;
      const newPath = currentPath.replace(/^\/(en|ar)/, `/${newLocale}`);
      window.location.href = newPath;
    }
  };

  const value = { locale, t, changeLanguage, isRTL: locale === 'ar', loading };

  return (
    <I18nContext.Provider value={value}>
      {i18nConfig.useSuspense && loading ? null : children}
    </I18nContext.Provider>
  );
}

export function useI18n() {
  const context = useContext(I18nContext);
  if (context === undefined) throw new Error('useI18n must be used within an I18nProvider');
  return context;
}
