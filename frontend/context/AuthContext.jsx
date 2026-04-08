'use client';

import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import api, { clearTokenCache } from '@/lib/api';

// ─── Types / Shape ────────────────────────────────────────────────────────────
/**
 * @typedef {Object} AuthUser
 * @property {number}   id
 * @property {string}   name
 * @property {string}   email
 * @property {string}   plan             - 'free' | 'paid'
 * @property {string[]} roles
 * @property {string[]} permissions
 * @property {number}   request_count
 * @property {boolean}  limit_reached
 * @property {number}   free_limit
 */

const AuthContext = createContext(undefined);

// ─── Role → dashboard path map ────────────────────────────────────────────────
function getDashboardPath(role) {
    if (role === 'admin') return '/dashboard/admin';
    if (role === 'customer') return '/dashboard/customer';
    if (role?.startsWith('provider')) return '/dashboard/provider';
    return '/dashboard/customer';
}

// ─── Provider ─────────────────────────────────────────────────────────────────
export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const router = useRouter();

    /**
     * Fetch the authenticated user from /api/me.
     * The Axios interceptor already attaches the token — this just verifies
     * the session is still valid and hydrates the user object.
     */
    const hydrateUser = useCallback(async () => {
        try {
            const res = await api.get('/me');
            setUser(res.data);
            return res.data;
        } catch (error) {
            setUser(null);
            return null;
        }
    }, []);

    // On first mount, attempt to restore session via the cookie
    useEffect(() => {
        const restore = async () => {
            // Ask the session route handler if we have a valid cookie
            try {
                const sessionRes = await fetch('/api/session');
                if (sessionRes.ok) {
                    await hydrateUser();
                }
            } catch {
                // No session — that's fine
            } finally {
                setLoading(false);
            }
        };

        restore();
    }, [hydrateUser]);

    const login = useCallback(async (token, userData) => {
        const primaryRole = userData.roles?.[0] ?? '';

        // Persist token + primary role in httpOnly cookie via Next.js API route
        await fetch('/api/session', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token, role: primaryRole }),
        });

        setUser(userData);

        return { ...userData, primaryRole, dashboardPath: getDashboardPath(primaryRole) };
    }, []);

    /**
     * logout()
     * Revokes the Sanctum token on the backend, then clears the session cookie.
     */
    const logout = useCallback(async () => {
        try {
            await api.post('/logout');
        } catch {
            // We no longer auto-redirect away from home, allowing public access
        } finally {
            clearTokenCache();                                    // invalidate in-memory token
            await fetch('/api/session', { method: 'DELETE' });   // delete httpOnly cookie
            setUser(null);
            router.push('/login');
        }
    }, [router]);

    /**
     * refreshUser()
     * Re-fetches /api/me — useful after an action that changes subscription state.
     * E.g. after submitting a request the customer dashboard can call this to
     * update request_count and limit_reached without a full page reload.
     */
    const refreshUser = useCallback(async () => {
        await hydrateUser();
    }, [hydrateUser]);

    return (
        <AuthContext.Provider value={{ user, loading, login, logout, refreshUser }}>
            {children}
        </AuthContext.Provider>
    );
}

// ─── Hook ─────────────────────────────────────────────────────────────────────
export function useAuth() {
    const context = useContext(AuthContext);
    if (context === undefined) {
        throw new Error('useAuth must be used within an <AuthProvider>.');
    }
    return context;
}
