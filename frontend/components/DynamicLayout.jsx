'use client';

import { useEffect } from 'react';
import { useI18n } from '@/context/I18nContext';

export default function DynamicLayout({ children }) {
  const { locale, isRTL } = useI18n();

  useEffect(() => {
    // Update HTML attributes when language changes
    const html = document.documentElement;
    html.lang = locale;
    html.dir = isRTL ? 'rtl' : 'ltr';

    // Update font classes based on language
    const body = document.body;
    body.className = body.className.replace(/font-\w+/g, '');
    
    if (isRTL) {
      body.classList.add('font-arabic');
      body.style.fontFamily = 'var(--font-ibm-plex-arabic)';
    } else {
      body.classList.add('font-sans');
      body.style.fontFamily = 'var(--font-inter)';
    }
  }, [locale, isRTL]);

  return <>{children}</>;
}
