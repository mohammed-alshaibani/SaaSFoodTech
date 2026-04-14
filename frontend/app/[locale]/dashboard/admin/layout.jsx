'use client';

import { useAuth } from '@/context/AuthContext';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { LogOut, User } from 'lucide-react';

export default function AdminLayout({ children }) {
    const { user, loading, logout } = useAuth();
    const router = useRouter();

    useEffect(() => {
        // middleware.ts is the primary guard; this is a client-side safety net
        if (!loading && (!user || !user.roles?.includes('admin'))) {
            router.replace('/login');
        }
    }, [user, loading, router]);

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-white">
                <div className="animate-spin rounded-full h-8 w-8 border-4 border-[#7C3AED] border-t-transparent" />
            </div>
        );
    }

    if (!user || !user.roles?.includes('admin')) {
        return null; // Will redirect
    }

    return (
        <div className="min-h-screen bg-[#F8FAFC] text-[#1E293B] font-sans">
            {/* Clean Top Navbar */}
            <header className="px-6 py-4 flex justify-between items-center bg-white border-b border-gray-100">
                <div className="flex items-center gap-2">
                    <span className="text-xs font-medium text-gray-400 uppercase tracking-wider">Admin Portal</span>
                </div>
                <div className="flex items-center gap-4">
                    <span className="text-sm font-semibold text-[#1E293B]">
                        {user?.name || 'Admin User'}
                    </span>
                    <div className="w-9 h-9 rounded-full bg-[#7C3AED]/10 flex items-center justify-center text-[#7C3AED]">
                        <User size={18} />
                    </div>
                    <button
                        onClick={logout}
                        className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    >
                        <LogOut size={16} />
                        <span className="hidden sm:inline">تسجيل الخروج</span>
                    </button>
                </div>
            </header>

            {/* Main Content */}
            <main>
                {children}
            </main>
        </div>
    );
}
