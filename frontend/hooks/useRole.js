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

    return {
        role,
        isRole: (targetRole) => role === targetRole,
        isAdmin: checkIsAdmin(role),
        isProvider: checkIsProvider(role),
        isCustomer: checkIsCustomer(role),
        dashboardPath,
        Role
    };
}
