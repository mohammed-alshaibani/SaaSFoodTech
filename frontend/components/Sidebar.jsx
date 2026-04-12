'use client';

import { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import LanguageToggle from '@/components/LanguageToggle';
import {
    LayoutDashboard,
    Users,
    CreditCard,
    FileText,
    LogOut,
    Menu,
    X,
    Crown,
    Package,
    ClipboardList,
    ShoppingBag,
} from 'lucide-react';

export default function Sidebar() {
    const pathname = usePathname();
    const { user, logout } = useAuth();
    const { t } = useI18n();
    const [isOpen, setIsOpen] = useState(false);

    const activeText = "text-[#7C3AED] font-bold bg-[#7C3AED]/5 border-r-4 border-[#7C3AED]";
    const inactiveText = "text-gray-600 hover:text-[#7C3AED] hover:bg-gray-50 border-r-4 border-transparent";

    // Role-based navigation items
    const getNavItems = () => {
        let role = 'customer';
        if (user?.roles?.[0]?.name) role = user.roles[0].name;
        else if (typeof user?.roles?.[0] === 'string') role = user.roles[0];
        else if (user?.parsed_role) role = user.parsed_role;
        else if (user?.role) role = user.role;

        // Admin navigation
        if (role === 'admin') {
            return [
                { href: '/dashboard/admin', icon: LayoutDashboard, label: t('sidebar.dashboard') || 'لوحة التحكم', exact: true },
                { href: '/dashboard/admin/users', icon: Users, label: t('sidebar.users') || 'المستخدمين', exact: false },
                { href: '/dashboard/admin/roles', icon: Crown, label: t('sidebar.roles') || 'الأدوار', exact: true },
                { href: '/dashboard/admin/permissions', icon: ClipboardList, label: t('sidebar.permissions') || 'الصلاحيات', exact: true },
                { href: '/dashboard/admin/plans', icon: CreditCard, label: t('sidebar.plans') || 'خطط الاشتراك', exact: true },
                { href: '/dashboard/admin/requests', icon: FileText, label: t('sidebar.requests') || 'طلبات الخدمة', exact: true },
            ];
        }

        // Provider navigation
        if (role === 'provider' || role === 'provider_admin' || role === 'provider_employee') {
            return [
                { href: '/dashboard/provider', icon: LayoutDashboard, label: t('sidebar.dashboard') || 'لوحة التحكم', exact: true },
                { href: '/dashboard/provider/subscriptions', icon: ShoppingBag, label: t('sidebar.mySubscriptions') || 'اشتراكاتي', exact: true },
                { href: '/dashboard/provider/requests', icon: ClipboardList, label: t('sidebar.myServiceRequests') || 'طلبات خدماتي', exact: true },
            ];
        }

        // Customer navigation (default)
        return [
            { href: '/dashboard/customer', icon: LayoutDashboard, label: t('sidebar.dashboard') || 'لوحة التحكم', exact: true },
            { href: '/dashboard/customer/plans', icon: Package, label: t('sidebar.myPlans') || 'خططي', exact: true },
            { href: '/dashboard/customer/requests', icon: ClipboardList, label: t('sidebar.myRequests') || 'طلباتي', exact: true },
        ];
    };

    const navItems = getNavItems();

    return (
        <>
            {/* Mobile Hamburger */}
            <button
                onClick={() => setIsOpen(true)}
                className="lg:hidden fixed top-6 z-40 p-3 bg-white text-[#1E293B] rounded-xl shadow-md right-6 border border-gray-100"
                id="hamburger-menu"
            >
                <Menu size={24} />
            </button>

            {/* Backdrop */}
            {isOpen && (
                <div
                    className="fixed inset-0 bg-[#1E293B]/20 backdrop-blur-sm z-50 lg:hidden"
                    onClick={() => setIsOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside className={`
                fixed top-0 bottom-0 z-50 w-[260px] bg-white border-l border-gray-100 transition-transform duration-300 ease-in-out font-sans flex flex-col
                ${isOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'}
                right-0
            `}>
                <div className="flex flex-col h-full overflow-y-auto custom-scrollbar">
                    {/* Header */}
                    <div className="flex items-center gap-3 p-6 mb-2">
                        <div className="w-9 h-9 rounded-xl flex items-center justify-center text-white shadow-sm" style={{ backgroundColor: '#1E293B' }}>
                            <Crown size={18} fill="currentColor" />
                        </div>
                        <span className="text-xl font-black tracking-tight text-[#1E293B]">FoodTechSaas</span>
                        <button onClick={() => setIsOpen(false)} className="lg:hidden ms-auto text-gray-400">
                            <X size={24} />
                        </button>
                    </div>

                    {/* Section: Main Nav */}
                    <div className="flex-1 py-4">
                        <nav className="flex flex-col gap-1">
                            {navItems.map((item) => {
                                const isActive = item.exact ? pathname === item.href : pathname.startsWith(item.href);
                                const Icon = item.icon;

                                return (
                                    <Link key={item.href} href={item.href} onClick={() => setIsOpen(false)}
                                        className={`flex items-center gap-3 px-6 py-3 text-sm transition-all ${isActive ? activeText : inactiveText}`}>
                                        <Icon size={20} className={isActive ? "text-[#7C3AED]" : "text-gray-400"} />
                                        <span>{item.label}</span>
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>

                    {/* Footer */}
                    <div className="p-4 mt-auto bg-gray-50/50 border-t border-gray-100">
                        <div className="space-y-3">
                            {/* Logout Button */}
                            <button
                                onClick={logout}
                                className="w-full flex items-center gap-3 p-3 rounded-xl bg-white border border-gray-200 hover:border-red-200 hover:bg-red-50/50 transition-all group"
                            >
                                <div className="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-[#1E293B] font-bold text-sm group-hover:bg-white group-hover:text-red-500 transition-colors">
                                    {user?.name?.[0]?.toUpperCase() || 'A'}
                                </div>
                                <span className="flex-1 text-sm font-bold text-gray-600 group-hover:text-red-500 text-right transition-colors">
                                    {t('common.logout') || 'تسجيل الخروج'}
                                </span>
                                <LogOut size={18} className="text-gray-400 group-hover:text-red-500 transition-colors" />
                            </button>

                            {/* Language Toggle */}
                            <div className="bg-white rounded-xl border border-gray-200 p-2">
                                <LanguageToggle />
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </>
    );
}
