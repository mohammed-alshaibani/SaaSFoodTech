'use client';

import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { useAuth } from './AuthenticationContext';

// Types
/**
 * @typedef {Object} Permission
 * @property {string} name
 * @property {string} description
 */

/**
 * @typedef {Object} Role
 * @property {string} name
 * @property {string} description
 */

const AuthorizationContext = createContext(undefined);

// Authorization Service Class
class AuthorizationService {
    constructor() {
        this.roleHierarchy = {
            'admin': 4,
            'provider_admin': 3,
            'provider_employee': 2,
            'customer': 1,
        };

        this.dashboardPaths = {
            'admin': '/dashboard/admin',
            'provider_admin': '/dashboard/provider',
            'provider_employee': '/dashboard/provider',
            'customer': '/dashboard/customer',
        };
    }

    hasRole(userRoles, requiredRole) {
        if (!userRoles || !Array.isArray(userRoles)) return false;
        
        return userRoles.includes(requiredRole);
    }

    hasAnyRole(userRoles, requiredRoles) {
        if (!userRoles || !Array.isArray(userRoles)) return false;
        
        return requiredRoles.some(role => userRoles.includes(role));
    }

    hasAllRoles(userRoles, requiredRoles) {
        if (!userRoles || !Array.isArray(userRoles)) return false;
        
        return requiredRoles.every(role => userRoles.includes(role));
    }

    hasMinimumRole(userRoles, minimumRole) {
        if (!userRoles || !Array.isArray(userRoles)) return false;
        
        const userLevel = Math.max(...userRoles.map(role => this.roleHierarchy[role] || 0));
        const requiredLevel = this.roleHierarchy[minimumRole] || 0;
        
        return userLevel >= requiredLevel;
    }

    hasPermission(userPermissions, requiredPermission) {
        if (!userPermissions || !Array.isArray(userPermissions)) return false;
        
        return userPermissions.includes(requiredPermission);
    }

    canAccessRoute(userRoles, route) {
        // Route-based access control
        const routePermissions = {
            '/dashboard/admin': ['admin'],
            '/dashboard/provider': ['provider_admin', 'provider_employee'],
            '/dashboard/customer': ['customer'],
            '/admin': ['admin'],
            '/subscription': ['customer', 'provider_admin', 'provider_employee'],
        };

        const requiredRoles = routePermissions[route];
        if (!requiredRoles) return true;

        return this.hasAnyRole(userRoles, requiredRoles);
    }

    getDashboardPath(userRoles) {
        if (!userRoles || !Array.isArray(userRoles)) return '/dashboard/customer';

        // Find the highest priority role
        const primaryRole = userRoles.reduce((highest, current) => {
            const currentLevel = this.roleHierarchy[current] || 0;
            const highestLevel = this.roleHierarchy[highest] || 0;
            return currentLevel > highestLevel ? current : highest;
        }, userRoles[0]);

        return this.dashboardPaths[primaryRole] || '/dashboard/customer';
    }

    getRoleDisplayName(role) {
        const displayNames = {
            'admin': 'Administrator',
            'provider_admin': 'Provider Admin',
            'provider_employee': 'Provider Employee',
            'customer': 'Customer',
        };

        return displayNames[role] || role;
    }

    getPermissionsByCategory(permissions) {
        if (!permissions || !Array.isArray(permissions)) return {};

        const categories = {
            'user': [],
            'request': [],
            'admin': [],
            'subscription': [],
        };

        permissions.forEach(permission => {
            const [category] = permission.split('.');
            if (categories[category]) {
                categories[category].push(permission);
            }
        });

        return categories;
    }
}

// Provider Component
export function AuthorizationProvider({ children }) {
    const { user, isAuthenticated } = useAuth();
    const [permissions, setPermissions] = useState([]);
    const [roles, setRoles] = useState([]);
    const [authorizationLoading, setAuthorizationLoading] = useState(false);

    const authService = new AuthorizationService();

    useEffect(() => {
        if (isAuthenticated && user) {
            setPermissions(user.permissions || []);
            setRoles(user.roles || []);
        } else {
            setPermissions([]);
            setRoles([]);
        }
    }, [isAuthenticated, user]);

    // Permission checking methods
    const hasRole = useCallback((role) => {
        return authService.hasRole(roles, role);
    }, [roles]);

    const hasAnyRole = useCallback((requiredRoles) => {
        return authService.hasAnyRole(roles, requiredRoles);
    }, [roles]);

    const hasAllRoles = useCallback((requiredRoles) => {
        return authService.hasAllRoles(roles, requiredRoles);
    }, [roles]);

    const hasMinimumRole = useCallback((minimumRole) => {
        return authService.hasMinimumRole(roles, minimumRole);
    }, [roles]);

    const hasPermission = useCallback((permission) => {
        return authService.hasPermission(permissions, permission);
    }, [permissions]);

    const canAccessRoute = useCallback((route) => {
        return authService.canAccessRoute(roles, route);
    }, [roles]);

    const getDashboardPath = useCallback(() => {
        return authService.getDashboardPath(roles);
    }, [roles]);

    // Utility methods
    const getRoleDisplayName = useCallback((role) => {
        return authService.getRoleDisplayName(role);
    }, []);

    const getPermissionsByCategory = useCallback(() => {
        return authService.getPermissionsByCategory(permissions);
    }, [permissions]);

    const isProvider = useCallback(() => {
        return authService.hasAnyRole(roles, ['provider_admin', 'provider_employee']);
    }, [roles]);

    const isAdmin = useCallback(() => {
        return authService.hasRole('admin');
    }, [roles]);

    const isCustomer = useCallback(() => {
        return authService.hasRole('customer');
    }, [roles]);

    const value = {
        // State
        permissions,
        roles,
        authorizationLoading,

        // Permission checking
        hasRole,
        hasAnyRole,
        hasAllRoles,
        hasMinimumRole,
        hasPermission,
        canAccessRoute,

        // Role checking
        isProvider,
        isAdmin,
        isCustomer,

        // Utilities
        getDashboardPath,
        getRoleDisplayName,
        getPermissionsByCategory,
    };

    return (
        <AuthorizationContext.Provider value={value}>
            {children}
        </AuthorizationContext.Provider>
    );
}

// Hook
export function useAuthorization() {
    const context = useContext(AuthorizationContext);
    if (context === undefined) {
        throw new Error('useAuthorization must be used within an AuthorizationProvider');
    }
    return context;
}

// Export service for testing
export { AuthorizationService };
