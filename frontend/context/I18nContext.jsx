'use client';

import { createContext, useContext, useState, useEffect } from 'react';

// Translations are now loaded dynamically from /public/locales/[locale]/common.json

// Context
const I18nContext = createContext(undefined);

// Provider
/**
 * I18nContext
 * 
 * ARCHITECTURAL NOTE: Kept for RTL support and future-readiness for the Arab market.
 * Provides lightweight translation strings and direction switching.
 */
export function I18nProvider({ children }) {
  const [locale, setLocale] = useState('ar'); // Default to Arabic
  const [translations, setTranslations] = useState({});
  const [loading, setLoading] = useState(true);

  // Load saved locale from localStorage on mount
  useEffect(() => {
    const savedLocale = localStorage.getItem('locale');
    if (savedLocale && (savedLocale === 'ar' || savedLocale === 'en')) {
      setLocale(savedLocale);
    }
  }, []);

  // Fetch translations when locale changes
  useEffect(() => {
    async function loadTranslations() {
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
    
    localStorage.setItem('locale', locale);
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = locale;
  }, [locale]);

  const t = (key) => {
    if (loading || !translations) return key;
    
    const keys = key.split('.');
    let value = translations;

    for (const k of keys) {
      value = value?.[k];
    }

    return value || key;
  };

  const changeLanguage = (newLocale) => {
    if (newLocale === 'ar' || newLocale === 'en') {
      setLocale(newLocale);
    }
  };

  const value = {
    locale,
    t,
    changeLanguage,
    isRTL: locale === 'ar',
    loading
  };

  return (
    <I18nContext.Provider value={value}>
      {children}
    </I18nContext.Provider>
  );
}

// Hook
export function useI18n() {
  const context = useContext(I18nContext);
  if (context === undefined) {
    throw new Error('useI18n must be used within an I18nProvider');
  }
  return context;
}
