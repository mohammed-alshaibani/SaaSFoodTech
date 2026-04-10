'use client';

import Sidebar from './Sidebar';
import { useI18n } from '@/context/I18nContext';

export default function DashboardLayout({ children }) {
    const { isRTL } = useI18n();

    return (
        <div className="min-h-screen bg-[#F8FAFC] text-[#1E293B] flex overflow-x-hidden">
            <Sidebar />
            <main className="flex-1 transition-all duration-300 lg:pr-[260px]">
                <div className="p-6 md:p-10 max-w-[1600px] mx-auto min-h-screen">
                    {children}
                </div>
            </main>
        </div>
    );
}
