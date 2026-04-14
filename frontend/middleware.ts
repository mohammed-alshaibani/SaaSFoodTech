import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

const locales = ['en', 'ar'];
const defaultLocale = 'ar';

function getLocale(request: NextRequest) {
    const cookieLocale = request.cookies.get('NEXT_LOCALE')?.value;
    if (cookieLocale && locales.includes(cookieLocale)) return cookieLocale;

    const acceptLanguage = request.headers.get('accept-language');
    if (acceptLanguage) {
        if (acceptLanguage.includes('en')) return 'en';
        if (acceptLanguage.includes('ar')) return 'ar';
    }

    return defaultLocale;
}

export function middleware(request: NextRequest) {
    const { pathname } = request.nextUrl;

    if (
        pathname.startsWith('/_next') ||
        pathname.startsWith('/api') ||
        pathname.includes('.') ||
        pathname === '/favicon.ico'
    ) {
        return NextResponse.next();
    }

    const pathnameHasLocale = locales.some(
        (locale) => pathname.startsWith(`/${locale}/`) || pathname === `/${locale}`
    );

    if (!pathnameHasLocale) {
        const locale = getLocale(request);
        const url = new URL(`/${locale}${pathname}`, request.url);
        request.nextUrl.searchParams.forEach((value, key) => {
            url.searchParams.set(key, value);
        });
        return NextResponse.redirect(url);
    }

    const currentLocale = pathname.split('/')[1];
    const token = request.cookies.get('auth_token')?.value;
    const role = request.cookies.get('auth_role')?.value;
    const l = (path: string) => `/${currentLocale}${path}`;

    const isDashboard = pathname.startsWith(l('/dashboard'));
    const isAuthPage = pathname.startsWith(l('/login')) || pathname.startsWith(l('/register'));

    if (isDashboard && !token) {
        return NextResponse.redirect(new URL(l('/login'), request.url));
    }

    if (isAuthPage && token) {
        const dashboardPath = getDashboardPath(role);
        return NextResponse.redirect(new URL(l(dashboardPath), request.url));
    }

    if (isDashboard) {
        const relativePath = pathname.replace(l(''), '');

        if (relativePath.startsWith('/dashboard/admin') && role !== 'admin') {
            return NextResponse.redirect(new URL(l(getDashboardPath(role)), request.url));
        }
        if (relativePath.startsWith('/dashboard/provider') && !['provider_admin', 'provider_employee'].includes(role || '')) {
            return NextResponse.redirect(new URL(l(getDashboardPath(role)), request.url));
        }
        if (relativePath.startsWith('/dashboard/customer') && role !== 'customer') {
            return NextResponse.redirect(new URL(l(getDashboardPath(role)), request.url));
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

export const config = {
    matcher: ['/((?!_next/static|_next/image|favicon.ico|api/).*)'],
};
