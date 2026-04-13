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
        <div className="min-h-screen bg-transparent">
            {children}
        </div>
    );
}
