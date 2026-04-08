import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

// ─── Constants ────────────────────────────────────────────────────────────────
const PUBLIC_PATHS = ['/login', '/register'];
const COOKIE_NAME = 'auth_token';

/**
 * Role → allowed path prefix map.
 * The middleware checks that the visiting role is allowed on the current path.
 */
const ROLE_PATH_MAP: Record<string, string> = {
    admin: '/dashboard/admin',
    customer: '/dashboard/customer',
    provider_admin: '/dashboard/provider',
    provider_employee: '/dashboard/provider',
};

// ─── Middleware ───────────────────────────────────────────────────────────────
export function middleware(request: NextRequest) {
    const { pathname } = request.nextUrl;

    // 1. Always allow Next.js internals and public assets
    if (
        pathname.startsWith('/_next') ||
        pathname.startsWith('/api') ||
        pathname.includes('.')
    ) {
        return NextResponse.next();
    }

    const token = request.cookies.get(COOKIE_NAME)?.value;
    const roleStr = request.cookies.get('auth_role')?.value; // e.g. "admin"
    const isPublic = PUBLIC_PATHS.includes(pathname);

    // 2. No token -> allow landing page, redirect to login for other protected paths
    if (!token) {
        if (pathname === '/' || isPublic) return NextResponse.next();
        return NextResponse.redirect(new URL('/login', request.url));
    }

    // 3. Token present + hitting a public auth page -> redirect to their dashboard
    if (isPublic) {
        const dest = roleStr ? (ROLE_PATH_MAP[roleStr] ?? '/') : '/';
        return NextResponse.redirect(new URL(dest, request.url));
    }

    // 4. Token present + hitting landing page -> redirect to dashboard
    if (pathname === '/') {
        const dest = roleStr ? (ROLE_PATH_MAP[roleStr] ?? '/') : '/';
        return NextResponse.redirect(new URL(dest, request.url));
    }

    // 5. Token present → enforce role-based path access
    if (roleStr) {
        const allowedPrefix = ROLE_PATH_MAP[roleStr];

        // Check if user is trying to access a role-restricted area they don't own
        const isAdminPath = pathname.startsWith('/dashboard/admin');
        const isCustomerPath = pathname.startsWith('/dashboard/customer');
        const isProviderPath = pathname.startsWith('/dashboard/provider');

        const accessingRestricted =
            (isAdminPath && roleStr !== 'admin') ||
            (isCustomerPath && roleStr !== 'customer') ||
            (isProviderPath && roleStr !== 'provider_admin' && roleStr !== 'provider_employee');

        if (accessingRestricted) {
            // Redirect to their own dashboard instead of 403
            const redirectDest = allowedPrefix ?? '/';
            return NextResponse.redirect(new URL(redirectDest, request.url));
        }
    }

    return NextResponse.next();
}

// ─── Matcher ─────────────────────────────────────────────────────────────────
// Run middleware on all routes EXCEPT api routes, static files, and _next internals
export const config = {
    matcher: [
        '/((?!_next/static|_next/image|favicon.ico|api/).*)',
    ],
};
