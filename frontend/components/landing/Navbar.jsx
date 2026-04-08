'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import { Menu, X, Rocket } from 'lucide-react';
import LanguageToggle from '@/components/auth/LanguageToggle';

export default function Navbar() {
    const [isOpen, setIsOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const { user } = useAuth();
    const { t, isRTL } = useI18n();

    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const navLinks = [
        { name: t('common.home') || 'Home', href: '#' },
        { name: t('common.features') || 'Features', href: '#features' },
        { name: t('common.pricing') || 'Pricing', href: '#pricing' },
        { name: t('common.faq') || 'FAQ', href: '#faq' },
    ];

    return (
        <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${scrolled ? 'bg-white/80 backdrop-blur-md shadow-sm py-3' : 'bg-transparent py-5'
            }`}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                {/* Logo */}
                <Link href="/" className="flex items-center gap-2">
                    <div className="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-600/20">
                        <Rocket size={20} />
                    </div>
                    <span className={`text-xl font-bold tracking-tight ${scrolled ? 'text-blue-600' : 'text-gray-900'}`}>
                        ServiceHub
                    </span>
                </Link>

                {/* Desktop Nav */}
                <div className="hidden md:flex items-center gap-8">
                    {navLinks.map((link) => (
                        <Link
                            key={link.name}
                            href={link.href}
                            className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
                        >
                            {link.name}
                        </Link>
                    ))}
                </div>

                {/* Auth Actions */}
                <div className="hidden md:flex items-center gap-4">
                    <LanguageToggle />
                    {user ? (
                        <>
                            <Link
                                href="/subscription"
                                className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
                            >
                                {t('common.subscription') || 'Subscription'}
                            </Link>
                            <Link
                                href="/admin" // or use dashboard path helper
                                className="px-5 py-2.5 rounded-full bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition-all active:scale-[0.98] shadow-md shadow-blue-600/10"
                            >
                                {t('common.dashboard') || 'Dashboard'}
                            </Link>
                        </>
                    ) : (
                        <>
                            <Link
                                href="/login"
                                className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
                            >
                                {t('auth.login')}
                            </Link>
                            <Link
                                href="/register"
                                className="px-5 py-2.5 rounded-full bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition-all active:scale-[0.98] shadow-md shadow-blue-600/10"
                            >
                                {t('auth.register')}
                            </Link>
                        </>
                    )}
                </div>

                {/* Mobile Menu Button */}
                <button
                    className="md:hidden p-2 text-gray-600 hover:text-blue-600 transition"
                    onClick={() => setIsOpen(!isOpen)}
                >
                    {isOpen ? <X /> : <Menu />}
                </button>
            </div>

            {/* Mobile Menu */}
            {isOpen && (
                <div className="md:hidden bg-white border-b px-4 py-8">
                    <div className="flex flex-col gap-6 items-center">
                        {navLinks.map((link) => (
                            <Link
                                key={link.name}
                                href={link.href}
                                onClick={() => setIsOpen(false)}
                                className="text-lg font-medium text-gray-800"
                            >
                                {link.name}
                            </Link>
                        ))}
                        <hr className="w-full border-gray-100" />
                        <LanguageToggle />
                        {user && (
                            <Link href="/subscription" className="text-gray-600 font-medium">
                                {t('common.subscription') || 'Subscription'}
                            </Link>
                        )}
                        <Link href="/login" className="text-gray-600 font-medium">{t('auth.login')}</Link>
                        <Link
                            href="/register"
                            className="w-full text-center px-5 py-3 rounded-xl bg-blue-600 text-white font-bold"
                        >
                            {t('auth.register')}
                        </Link>
                    </div>
                </div>
            )}
        </nav>
    );
}
