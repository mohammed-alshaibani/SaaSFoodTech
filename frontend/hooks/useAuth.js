'use client';

import { createContext, useContext, useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useI18n } from './I18nContext';
import api, { clearTokenCache } from '@/lib/api';

// Types
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

// Role -> dashboard path map
function getDashboardPath(role) {
  if (role === 'admin') return '/dashboard/admin';
  if (role === 'customer') return '/dashboard/customer';
  if (role?.startsWith('provider')) return '/dashboard/provider';
  return '/dashboard/customer';
}

// Provider
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [authLoading, setAuthLoading] = useState(false);
  const router = useRouter();
  const { t } = useI18n();

  /**
   * Fetch the authenticated user from /api/me.
   */
  const hydrateUser = useCallback(async () => {
    try {
      const res = await api.get('/me');
      setUser(res.data);
      return res.data;
    } catch {
      setUser(null);
      return null;
    }
  }, []);

  // On first mount, attempt to restore session via the cookie
  useEffect(() => {
    const restore = async () => {
      try {
        const sessionRes = await fetch('/api/session');
        if (sessionRes.ok) {
          await hydrateUser();
        }
      } catch {
        // No session - that's fine
      } finally {
        setLoading(false);
      }
    };

    restore();
  }, [hydrateUser]);

  /**
   * login(email, password)
   * Handles login with loading state and error handling
   */
  const login = useCallback(async (email, password) => {
    setAuthLoading(true);
    try {
      const res = await api.post('/login', { email, password });
      const { token, user: userData } = res.data;
      
      const primaryRole = userData.roles?.[0] ?? '';
      
      // Strip internal fields before storing in state
      const { _redirectOverride: _ignored, ...cleanUser } = userData;

      // Persist token + primary role in httpOnly cookie
      await fetch('/api/session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, role: primaryRole }),
      });

      setUser(cleanUser);
      
      // Redirect to dashboard directly (clean URL)
      router.push(getDashboardPath(primaryRole));
      
      return { success: true };
    } catch (error) {
      const message = error.response?.data?.message || t('auth.loginError');
      return { success: false, error: message };
    } finally {
      setAuthLoading(false);
    }
  }, [router, t]);

  /**
   * register(name, email, password, passwordConfirmation)
   * Handles registration with loading state and error handling
   */
  const register = useCallback(async (name, email, password, passwordConfirmation) => {
    setAuthLoading(true);
    try {
      const res = await api.post('/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      
      const { token, user: userData } = res.data;
      const primaryRole = userData.roles?.[0] ?? '';
      
      // Strip internal fields before storing in state
      const { _redirectOverride: _ignored, ...cleanUser } = userData;

      // Persist token + primary role in httpOnly cookie
      await fetch('/api/session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, role: primaryRole }),
      });

      setUser(cleanUser);
      
      // Redirect to dashboard directly (clean URL)
      router.push(getDashboardPath(primaryRole));
      
      return { success: true };
    } catch (error) {
      const message = error.response?.data?.message || t('auth.registerError');
      return { success: false, error: message };
    } finally {
      setAuthLoading(false);
    }
  }, [router, t]);

  /**
   * logout()
   * Revokes the Sanctum token on the backend, then clears the session cookie.
   */
  const logout = useCallback(async () => {
    try {
      await api.post('/logout');
    } catch {
      // Even if the backend call fails, clear the session locally
    } finally {
      clearTokenCache();
      await fetch('/api/session', { method: 'DELETE' });
      setUser(null);
      router.push('/login');
    }
  }, [router]);

  /**
   * refreshUser()
   * Re-fetches /api/me - useful after actions that change subscription state.
   */
  const refreshUser = useCallback(async () => {
    await hydrateUser();
  }, [hydrateUser]);

  return (
    <AuthContext.Provider value={{ 
      user, 
      loading, 
      authLoading,
      login, 
      register, 
      logout, 
      refreshUser 
    }}>
      {children}
    </AuthContext.Provider>
  );
}

// Hook
export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an <AuthProvider>.');
  }
  return context;
}
