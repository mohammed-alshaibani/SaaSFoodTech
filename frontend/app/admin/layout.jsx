'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';

const NAV = [
    { href: '/admin', label: '📊 Dashboard', exact: true },
    { href: '/admin/permissions', label: '🔐 Permission Control', exact: false },
];

export default function AdminLayout({ children }) {
    const { user, loading, logout } = useAuth();
    const router = useRouter();
    const pathname = usePathname();

    useEffect(() => {
        // middleware.ts is the primary guard; this is a client-side safety net
        if (!loading && (!user || !user.roles?.includes('admin'))) {
            router.replace('/login');
        }
    }, [user, loading, router]);

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50">
                <div className="animate-spin rounded-full h-8 w-8 border-4 border-indigo-500 border-t-transparent" />
            </div>
        );
    }

    if (!user || !user.roles?.includes('admin')) {
        return null; // Will redirect
    }

    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950">
            {/* Header */}
            <header className="bg-white dark:bg-slate-900 shadow-sm border-b border-slate-200 dark:border-slate-800">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16 items-center">
                        <div className="flex items-center gap-8">
                            <h1 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                🍔 FoodTech Admin
                            </h1>
                            <nav className="hidden md:flex gap-1">
                                {NAV.map(({ href, label, exact }) => {
                                    const active = exact ? pathname === href : pathname.startsWith(href);
                                    return (
                                        <Link
                                            key={href}
                                            href={href}
                                            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${active
                                                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'
                                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
                                                }`}
                                        >
                                            {label}
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>
                        <div className="flex items-center gap-4">
                            <span className="text-sm text-slate-500 dark:text-slate-400">
                                {user.name}
                            </span>
                            <button
                                id="admin-logout-btn"
                                onClick={logout}
                                className="text-sm text-red-500 hover:text-red-700 font-medium transition-colors"
                            >
                                Logout
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            {/* Main Content */}
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {children}
            </main>
        </div>
    );
}
