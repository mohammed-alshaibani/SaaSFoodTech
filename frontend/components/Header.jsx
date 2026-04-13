'use client';

import { useState, useRef, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import { useRole } from '@/hooks/useRole';
import {
    User,
    LogOut,
    ChevronDown,
    Settings,
    Shield,
    Bell,
    CheckCircle
} from 'lucide-react';
import NotificationBell from './NotificationBell';

export default function Header() {
    const { user, logout } = useAuth();
    const { t, language } = useI18n();
    const { roleLabel } = useRole();
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const menuRef = useRef(null);
    const isRTL = language === 'ar';

    useEffect(() => {
        function handleClickOutside(event) {
            if (menuRef.current && !menuRef.current.contains(event.target)) {
                setIsMenuOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    return (
        <header className="sticky top-0 z-30 w-full bg-white/80 backdrop-blur-md border-b border-gray-100 px-6 py-3 flex items-center justify-between">
            <div className="flex items-center gap-4">
                {/* Header title or search can go here */}
            </div>

            <div className="flex items-center gap-4">
                {/* Notifications Bell */}
                <NotificationBell />

                <div className="h-8 w-px bg-gray-100 mx-2"></div>

                {/* Profile Dropdown */}
                <div className="relative" ref={menuRef}>
                    <button
                        onClick={() => setIsMenuOpen(!isMenuOpen)}
                        className="flex items-center gap-3 p-1 rounded-2xl hover:bg-gray-50 transition-all"
                    >
                        <div className="w-10 h-10 rounded-xl bg-navy text-white flex items-center justify-center font-black shadow-sm">
                            {user?.name?.[0]?.toUpperCase() || 'U'}
                        </div>
                        <div className={`hidden md:block ${isRTL ? 'text-right' : 'text-left'}`}>
                            <p className="text-sm font-black text-navy leading-none">{user?.name}</p>
                            <p className="text-[10px] font-bold text-primary uppercase tracking-tighter mt-1">{roleLabel}</p>
                        </div>
                        <ChevronDown size={14} className={`text-gray-400 transition-transform ${isMenuOpen ? 'rotate-180' : ''}`} />
                    </button>

                    {/* Dropdown Menu */}
                    {isMenuOpen && (
                        <div className={`
                            absolute mt-3 w-64 bg-white rounded-2xl shadow-2xl border border-gray-100 py-2 z-50
                            ${isRTL ? 'left-0' : 'right-0'}
                            animate-in fade-in zoom-in-95 duration-200
                        `}>
                            <div className="px-4 py-3 border-b border-gray-50 mb-2">
                                <p className="text-xs font-bold text-gray-400 uppercase tracking-widest">{t('common.signedInAs') || 'SignedInAs'}</p>
                                <p className="text-sm font-black text-navy mt-1 truncate">{user?.email}</p>
                            </div>

                            <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 hover:text-navy transition-colors">
                                <User size={18} className="text-gray-400" />
                                <span>{t('header.profile') || 'الملف الشخصي'}</span>
                            </button>

                            <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 hover:text-navy transition-colors">
                                <Settings size={18} className="text-gray-400" />
                                <span>{t('header.settings') || 'الإعدادات'}</span>
                            </button>

                            {user?.active_subscription && (
                                <div className="mx-2 my-2 px-3 py-2 bg-emerald-50 rounded-xl border border-emerald-100 flex items-center gap-2">
                                    <CheckCircle size={14} className="text-emerald-500" />
                                    <span className="text-[10px] font-black text-emerald-700 uppercase tracking-tight">Active {user.plan} Plan</span>
                                </div>
                            )}

                            <div className="h-px bg-gray-50 my-2 mx-4"></div>

                            <button
                                onClick={logout}
                                className="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-50 transition-colors"
                            >
                                <LogOut size={18} />
                                <span className="font-bold">{t('common.logout') || 'Sign out'}</span>
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
