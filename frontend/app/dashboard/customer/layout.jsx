'use client';

import { useAuth } from '@/context/AuthContext';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';

export default function CustomerLayout({ children }) {
    const { user, loading, logout } = useAuth();
    const router = useRouter();

    useEffect(() => {
        // middleware.ts is the primary guard; this is a client-side safety net
        if (!loading && (!user || !user.roles?.includes('customer'))) {
            router.replace('/login');
        }
    }, [user, loading, router]);

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    if (!user || !user.roles?.includes('customer')) {
        return null; // Will redirect
    }

    return (
        <div className="min-h-screen bg-transparent">
            {children}
        </div>
    );
}
