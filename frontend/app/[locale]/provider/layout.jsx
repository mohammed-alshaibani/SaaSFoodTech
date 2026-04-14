'use client';

import { useAuth } from '@/context/AuthContext';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';

const PROVIDER_ROLES = ['provider_admin', 'provider_employee'];

export default function ProviderLayout({ children }) {
    const { user, loading, logout } = useAuth();
    const router = useRouter();

    useEffect(() => {
        // middleware.ts is the primary guard; this is a client-side safety net
        const isProvider = user?.roles?.some(r => PROVIDER_ROLES.includes(r));
        if (!loading && (!user || !isProvider)) {
            router.replace('/login');
        }
    }, [user, loading, router]);

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-green-500" />
            </div>
        );
    }

    if (!user) return null;

    // Display the most specific role
    const primaryRole = user.roles?.find(r => PROVIDER_ROLES.includes(r)) ?? 'provider';
    const roleLabel = primaryRole === 'provider_admin' ? 'Provider Admin' : 'Provider';

    return (
        <div className="min-h-screen bg-gray-50">
            <nav className="bg-white shadow-sm border-b">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16 items-center">
                        <div className="flex items-center gap-3">
                            <span className="text-xl font-bold text-green-600">ServiceHub</span>
                            <span className="text-gray-300">|</span>
                            <span className="text-sm text-gray-500">Provider Portal</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full font-semibold">
                                {roleLabel}
                            </span>
                            <span className="text-sm text-gray-700 font-medium">{user.name}</span>
                            <button
                                onClick={logout}
                                className="text-sm text-red-500 hover:text-red-700 transition"
                            >
                                Sign out
                            </button>
                        </div>
                    </div>
                </div>
            </nav>
            <main className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                {children}
            </main>
        </div>
    );
}
