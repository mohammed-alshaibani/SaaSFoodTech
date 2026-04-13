'use client';

import { useMemo } from 'react';
import { useAuth } from '@/context/AuthContext';
import {
    getNormalizedRole,
    getDashboardPath,
    checkIsAdmin,
    checkIsProvider,
    checkIsCustomer,
    Role
} from '@/lib/roles';

/**
 * useRole Hook
 * Provides reactive role state and semantic checks.
 */
export function useRole() {
    const { user } = useAuth();

    const role = useMemo(() => getNormalizedRole(user), [user]);

    const dashboardPath = useMemo(() => getDashboardPath(role), [role]);

    const roleLabel = useMemo(() => {
        if (!role) return 'Guest';
        return role.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    }, [role]);

    return {
        role,
        roleLabel,
        isRole: (targetRole) => role === targetRole,
        isAdmin: checkIsAdmin(role),
        isProvider: checkIsProvider(role),
        isCustomer: checkIsCustomer(role),
        dashboardPath,
        Role
    };
}
