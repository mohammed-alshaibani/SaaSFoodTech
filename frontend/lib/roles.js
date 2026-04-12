/**
 * Role Normalization Utility
 * Handles various role formats from the backend (object vs string)
 * and provides semantic helpers.
 */

export const Role = {
    ADMIN: 'admin',
    CUSTOMER: 'customer',
    PROVIDER_ADMIN: 'provider_admin',
    PROVIDER_EMPLOYEE: 'provider_employee',
    PROVIDER: 'provider', // Generic fallback
};

/**
 * Normalizes a user's role into a single string.
 * @param {Object} user 
 * @returns {string}
 */
export function getNormalizedRole(user) {
    if (!user) return Role.CUSTOMER;

    let role = null;

    // Check various common formats in the codebase
    if (user.roles && user.roles.length > 0) {
        const firstRole = user.roles[0];
        role = typeof firstRole === 'string' ? firstRole : firstRole.name;
    } else if (user.parsed_role) {
        role = user.parsed_role;
    } else if (user.role) {
        role = user.role;
    }

    return role || Role.CUSTOMER;
}

/**
 * Returns the appropriate dashboard base path for a role.
 * @param {string} role 
 * @returns {string}
 */
export function getDashboardPath(role) {
    if (role === Role.ADMIN) return '/dashboard/admin';
    if (role?.startsWith('provider')) return '/dashboard/provider';
    return '/dashboard/customer';
}

/**
 * Semantic role checks
 */
export const checkIsAdmin = (role) => role === Role.ADMIN;
export const checkIsProvider = (role) => role?.startsWith('provider');
export const checkIsCustomer = (role) => role === Role.CUSTOMER;
