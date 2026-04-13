'use client';

import Sidebar from './Sidebar';
import Header from './Header';
import { useI18n } from '@/context/I18nContext';

export default function DashboardLayout({ children }) {
    const { language } = useI18n();
    const isRTL = language === 'ar';

    return (
        <div className={`min-h-screen bg-slate-50 text-navy flex overflow-x-hidden ${isRTL ? 'flex-row-reverse' : 'flex-row'}`}>
            <Sidebar />
            <main className={`flex-1 transition-all duration-300 ${isRTL ? 'lg:pl-[260px]' : 'lg:pr-[260px]'} flex flex-col`}>
                <Header />
                <div className="p-6 md:p-10 max-w-[1600px] mx-auto w-full min-h-[calc(100-64px)]">
                    {children}
                </div>
            </main>
        </div>
    );
}
