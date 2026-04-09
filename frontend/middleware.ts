import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

// Simple middleware - just allow access to prevent redirect loops
export function middleware(request: NextRequest) {
    const { pathname } = request.nextUrl;

    // Always allow Next.js internals and public assets
    if (
        pathname.startsWith('/_next') ||
        pathname.startsWith('/api') ||
        pathname.includes('.')
    ) {
        return NextResponse.next();
    }

    // Temporarily allow all routes to prevent redirect loops
    return NextResponse.next();
}

// Matcher
export const config = {
    matcher: [
        '/((?!_next/static|_next/image|favicon.ico|api/).*)',
    ],
};
