import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

// Simple middleware - just allow access to prevent redirect loops
export function middleware(request: NextRequest) {
    const { pathname } = request.nextUrl;
    const token = request.cookies.get('auth_token')?.value;
    const role = request.cookies.get('auth_role')?.value;

    // Public patterns for Next.js internal and assets
    if (
        pathname.startsWith('/_next') ||
        pathname.startsWith('/api') ||
        pathname.includes('.')
    ) {
        return NextResponse.next();
    }

    const isDashboard = pathname.startsWith('/dashboard');
    const isAuthPage = pathname.startsWith('/login') || pathname.startsWith('/register');

    // 1. Redirect unauthenticated users from dashboard
    if (isDashboard && !token) {
        return NextResponse.redirect(new URL('/login', request.url));
    }

    // 2. Redirect authenticated users away from login/register
    if (isAuthPage && token) {
        const dashboardPath = getDashboardPath(role);
        return NextResponse.redirect(new URL(dashboardPath, request.url));
    }

    // 3. Role-based granular protection for dashboard sub-paths
    if (isDashboard) {
        if (pathname.startsWith('/dashboard/admin') && role !== 'admin') {
            return NextResponse.redirect(new URL(getDashboardPath(role), request.url));
        }
        if (pathname.startsWith('/dashboard/provider') && !['provider_admin', 'provider_employee'].includes(role || '')) {
            return NextResponse.redirect(new URL(getDashboardPath(role), request.url));
        }
        if (pathname.startsWith('/dashboard/customer') && role !== 'customer') {
            return NextResponse.redirect(new URL(getDashboardPath(role), request.url));
        }
    }

    // 4. Permission-based route protection (for specific routes)
    // This is a simplified check - full permission checking happens in components
    const permissionRoutes = {
        '/dashboard/admin/roles': 'role.view',
        '/dashboard/admin/permissions': 'permission.view',
        '/dashboard/admin/users': 'user.manage',
    };

    const requiredPermission = permissionRoutes[pathname];
    if (requiredPermission && token) {
        // For permission-based routes, we can't check permissions here since
        // we don't have the user's full permission list in middleware.
        // This check is done in the component level using useAuthorization hook.
        // We just ensure the user is authenticated and has the right role.
        if (pathname.startsWith('/dashboard/admin') && role !== 'admin') {
            return NextResponse.redirect(new URL(getDashboardPath(role), request.url));
        }
    }

    return NextResponse.next();
}

function getDashboardPath(role?: string): string {
    switch (role) {
        case 'admin': return '/dashboard/admin';
        case 'provider_admin':
        case 'provider_employee': return '/dashboard/provider';
        default: return '/dashboard/customer';
    }
}

// Matcher
export const config = {
    matcher: [
        '/((?!_next/static|_next/image|favicon.ico|api/).*)',
    ],
};
