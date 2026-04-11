'use client';

import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { useAuth } from './AuthenticationContext';

// Permission cache with TTL (5 minutes)
const PERMISSION_CACHE_TTL = 5 * 60 * 1000; // 5 minutes in milliseconds
let permissionCache = {
    data: null,
    timestamp: 0
};

/**
 * Invalidate permission cache - call this when permissions change
 */
export function invalidatePermissionCache() {
    permissionCache = {
        data: null,
        timestamp: 0
    };
}

/**
 * Check if cache is valid
 */
function isCacheValid() {
    if (!permissionCache.data) return false;
    return Date.now() - permissionCache.timestamp < PERMISSION_CACHE_TTL;
}

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
    constructor(hierarchyData = null) {
        // Build hierarchy map from backend data or fallback to hardcoded
        this.roleHierarchy = this.buildHierarchyMap(hierarchyData);

        this.dashboardPaths = {
            'admin': '/dashboard/admin',
            'provider_admin': '/dashboard/provider',
            'provider_employee': '/dashboard/provider',
            'customer': '/dashboard/customer',
        };
    }

    buildHierarchyMap(hierarchyData) {
        if (!hierarchyData || !Array.isArray(hierarchyData)) {
            // Fallback to hardcoded hierarchy if no data
            return {
                'admin': 4,
                'provider_admin': 3,
                'provider_employee': 2,
                'customer': 1,
            };
        }

        // Build level map from hierarchy tree
        const levelMap = {};
        const traverse = (roles, level) => {
            roles.forEach(role => {
                levelMap[role.name] = level;
                if (role.childRoles && role.childRoles.length > 0) {
                    traverse(role.childRoles, level + 1);
                }
            });
        };

        traverse(hierarchyData, 1);
        return levelMap;
    }

    getInheritedRoles(userRoles, hierarchyData) {
        if (!hierarchyData || !Array.isArray(hierarchyData)) return userRoles;

        const inherited = new Set(userRoles);
        const allRoles = [];

        const traverse = (roles) => {
            roles.forEach(role => {
                allRoles.push(role);
                if (role.childRoles && role.childRoles.length > 0) {
                    traverse(role.childRoles);
                }
            });
        };

        traverse(hierarchyData);

        // Find user's roles and add their children
        userRoles.forEach(userRole => {
            const findAndAddChildren = (roles) => {
                roles.forEach(role => {
                    if (role.name === userRole && role.childRoles) {
                        role.childRoles.forEach(child => {
                            inherited.add(child.name);
                            findAndAddChildren([child]);
                        });
                    }
                    if (role.childRoles) {
                        findAndAddChildren(role.childRoles);
                    }
                });
            };
            findAndAddChildren(hierarchyData);
        });

        return Array.from(inherited);
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

    hasPermissionWithContext(userPermissions, requiredPermission, context = {}) {
        if (!userPermissions || !Array.isArray(userPermissions)) return false;
        
        // Check if user has the base permission
        if (!userPermissions.includes(requiredPermission)) {
            return false;
        }

        // If no scope context, permission check passes
        if (!context || !context.scope) {
            return true;
        }

        // Handle scoped permission checks
        switch (context.scope) {
            case 'self':
                return this.checkSelfScope(context);
            case 'location':
                return this.checkLocationScope(context);
            case 'department':
                return this.checkDepartmentScope(context);
            case 'team':
                return this.checkTeamScope(context);
            default:
                return true;
        }
    }

    checkSelfScope(context) {
        if (!context.resource || !context.userId) return true;
        
        // Check if resource belongs to user
        if (context.resource.customer_id === context.userId) return true;
        if (context.resource.provider_id === context.userId) return true;
        if (context.resource.id === context.resourceId) return true;
        
        return false;
    }

    checkLocationScope(context) {
        if (!context.userLocation || !context.resourceLocation) return true;
        
        const distance = this.calculateDistance(
            context.userLocation.lat,
            context.userLocation.lng,
            context.resourceLocation.lat,
            context.resourceLocation.lng
        );
        
        const maxDistance = context.maxDistance || 50; // Default 50km
        return distance <= maxDistance;
    }

    checkDepartmentScope(context) {
        if (!context.userDepartment || !context.allowedDepartments) return true;
        
        return context.allowedDepartments.includes(context.userDepartment);
    }

    checkTeamScope(context) {
        if (!context.userTeamId || !context.allowedTeams) return true;
        
        return context.allowedTeams.includes(context.userTeamId);
    }

    calculateDistance(lat1, lon1, lat2, lon2) {
        const earthRadius = 6371; // Earth's radius in kilometers

        const dLat = this.toRad(lat2 - lat1);
        const dLon = this.toRad(lon2 - lon1);

        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);

        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

        return earthRadius * c;
    }

    toRad(degrees) {
        return degrees * (Math.PI / 180);
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
    const [roleHierarchy, setRoleHierarchy] = useState(null);
    const [hierarchyLoading, setHierarchyLoading] = useState(false);

    const authService = new AuthorizationService(roleHierarchy);

    // Note: Role hierarchy fetch disabled - /api/roles/hierarchy endpoint not implemented in backend

    useEffect(() => {
        if (isAuthenticated && user) {
            // Use cache if valid, otherwise update from user data
            if (isCacheValid() && permissionCache.data) {
                setPermissions(permissionCache.data.permissions || []);
                setRoles(permissionCache.data.roles || []);
            } else {
                const newPermissions = user.permissions || [];
                const newRoles = user.roles || [];
                setPermissions(newPermissions);
                setRoles(newRoles);
                
                // Update cache
                permissionCache = {
                    data: { permissions: newPermissions, roles: newRoles },
                    timestamp: Date.now()
                };
            }
        } else {
            setPermissions([]);
            setRoles([]);
            // Clear cache on logout
            invalidatePermissionCache();
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

    const hasPermissionWithContext = useCallback((permission, context) => {
        return authService.hasPermissionWithContext(permissions, permission, context);
    }, [permissions]);

    const canAccessOwnResource = useCallback((resource, userId) => {
        return authService.checkSelfScope({ resource, userId });
    }, []);

    const canAccessWithinLocation = useCallback((userLocation, resourceLocation, maxDistance) => {
        return authService.checkLocationScope({ userLocation, resourceLocation, maxDistance });
    }, []);

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
        // Check if user has admin role - this is dynamic based on backend response
        // The backend's Gate::before grants admin all permissions
        return authService.hasRole('admin');
    }, [roles]);

    const isCustomer = useCallback(() => {
        return authService.hasRole('customer');
    }, [roles]);

    const getAllPermissions = useCallback(() => {
        // Get permissions including inherited from role hierarchy
        const inheritedRoles = authService.getInheritedRoles(roles, roleHierarchy);
        const allPermissions = new Set(permissions);
        
        // If backend returns permissions for all roles including inherited, use that
        // Otherwise, this would need to fetch permissions for inherited roles
        return Array.from(allPermissions);
    }, [roles, permissions, roleHierarchy, authService]);

    const getInheritedRoles = useCallback(() => {
        return authService.getInheritedRoles(roles, roleHierarchy);
    }, [roles, roleHierarchy, authService]);

    const value = {
        // State
        permissions,
        roles,
        authorizationLoading,
        hierarchyLoading,
        roleHierarchy,

        // Permission checking
        hasRole,
        hasAnyRole,
        hasAllRoles,
        hasMinimumRole,
        hasPermission,
        hasPermissionWithContext,
        canAccessOwnResource,
        canAccessWithinLocation,
        canAccessRoute,

        // Role checking
        isProvider,
        isAdmin,
        isCustomer,

        // Hierarchy methods
        getAllPermissions,
        getInheritedRoles,

        // Cache management
        invalidatePermissionCache,

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
