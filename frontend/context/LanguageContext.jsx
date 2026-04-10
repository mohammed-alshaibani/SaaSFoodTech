'use client';

import { createContext, useContext, useState, useEffect } from 'react';

const LanguageContext = createContext();

export const LanguageProvider = ({ children }) => {
    const [lang, setLang] = useState('en');

    useEffect(() => {
        const saved = localStorage.getItem('app_lang');
        if (saved) setLang(saved);
    }, []);

    const toggleLang = () => {
        const next = lang === 'en' ? 'ar' : 'en';
        setLang(next);
        localStorage.setItem('app_lang', next);
        document.dir = next === 'ar' ? 'rtl' : 'ltr';
    };

    const t = (en, ar) => (lang === 'en' ? en : ar);

    return (
        <LanguageContext.Provider value={{ lang, toggleLang, t }}>
            <div dir={lang === 'ar' ? 'rtl' : 'ltr'} className={lang === 'ar' ? 'font-arabic' : ''}>
                {children}
            </div>
        </LanguageContext.Provider>
    );
};

export const useLanguage = () => useContext(LanguageContext);
