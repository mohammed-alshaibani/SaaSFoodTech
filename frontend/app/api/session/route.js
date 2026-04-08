import { cookies } from 'next/headers';
import { NextResponse } from 'next/server';

const IS_PROD = process.env.NODE_ENV === 'production';
const MAX_AGE = 60 * 60 * 24 * 7; // 7 days in seconds
const TOKEN_KEY = 'auth_token';
const ROLE_KEY = 'auth_role';

/**
 * POST /api/session
 * Called by AuthContext after a successful login or register.
 * Sets two httpOnly cookies:
 *  - auth_token  → the Sanctum Bearer token
 *  - auth_role   → the user's primary role (used by middleware.ts for path guards)
 *
 * Body: { token: string, role: string }
 */
export async function POST(request) {
    try {
        const { token, role } = await request.json();

        if (!token || typeof token !== 'string') {
            return NextResponse.json({ error: 'Missing token' }, { status: 400 });
        }

        const cookieStore = cookies();
        const cookieOptions = {
            httpOnly: true,
            secure: IS_PROD,
            sameSite: 'lax',
            maxAge: MAX_AGE,
            path: '/',
        };

        cookieStore.set(TOKEN_KEY, token, cookieOptions);
        cookieStore.set(ROLE_KEY, role ?? '', {
            ...cookieOptions,
            httpOnly: false, // role is NOT sensitive — middleware reads it server-side anyway
        });

        return NextResponse.json({ ok: true });
    } catch (error) {
        return NextResponse.json({ error: 'Invalid request body' }, { status: 400 });
    }
}

/**
 * DELETE /api/session
 * Called by AuthContext on logout.
 * Clears both auth cookies immediately.
 */
export async function DELETE() {
    const cookieStore = cookies();
    const expireOptions = {
        httpOnly: true,
        secure: IS_PROD,
        sameSite: 'lax',
        maxAge: 0, // expire immediately
        path: '/',
    };

    cookieStore.set(TOKEN_KEY, '', expireOptions);
    cookieStore.set(ROLE_KEY, '', { ...expireOptions, httpOnly: false });

    return NextResponse.json({ ok: true });
}

/**
 * GET /api/session
 * Returns the current session token for the Axios interceptor to read.
 * Note: httpOnly cookies are NOT accessible to JS, but this route handler
 * CAN read them server-side and forward the token to our axios instance.
 */
export async function GET() {
    const cookieStore = cookies();
    const token = cookieStore.get('auth_token')?.value;

    if (!token) {
        return NextResponse.json({ token: null }, { status: 401 });
    }

    return NextResponse.json({ token });
}
