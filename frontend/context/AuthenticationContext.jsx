'use client';

import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';

// Types
/**
 * @typedef {Object} AuthUser
 * @property {number} id
 * @property {string} name
 * @property {string} email
 * @property {string} plan
 * @property {number} request_count
 * @property {boolean} limit_reached
 * @property {number} free_limit
 */

const AuthenticationContext = createContext(undefined);

// Authentication Service Class
class AuthenticationService {
    constructor() {
        this.baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
    }

    async login(credentials) {
        const response = await fetch(`${this.baseUrl}/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(credentials),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Login failed');
        }

        return data;
    }

    async logout() {
        try {
            await api.post('/logout');
        } catch (error) {
            console.error('Logout error:', error);
        }
    }

    async getCurrentUser() {
        try {
            const response = await api.get('/me');
            return response.data;
        } catch (error) {
            return null;
        }
    }
}

// Session Manager Class
class SessionManager {
    async saveSession(token, userData) {
        await fetch('/api/session', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token, user: userData }),
        });
    }

    async clearSession() {
        await fetch('/api/session', { method: 'DELETE' });
    }

    async hasValidSession() {
        try {
            const response = await fetch('/api/session');
            return response.ok;
        } catch {
            return false;
        }
    }
}

// Provider Component
export function AuthenticationProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const authService = new AuthenticationService();
    const sessionManager = new SessionManager();

    const hydrateUser = useCallback(async () => {
        try {
            const userData = await authService.getCurrentUser();
            setUser(userData);
            setError(null);
            return userData;
        } catch (error) {
            setUser(null);
            setError(error.message);
            return null;
        }
    }, []);

    useEffect(() => {
        const restore = async () => {
            try {
                const hasSession = await sessionManager.hasValidSession();
                if (hasSession) {
                    await hydrateUser();
                }
            } catch (error) {
                setError(error.message);
            } finally {
                setLoading(false);
            }
        };

        restore();
    }, [hydrateUser]);

    const login = useCallback(async (credentials) => {
        setLoading(true);
        setError(null);

        try {
            const data = await authService.login(credentials);
            const { access_token, user: userData } = data;

            await sessionManager.saveSession(access_token, userData);
            setUser(userData);

            return { success: true, user: userData };
        } catch (error) {
            setError(error.message);
            return { success: false, error: error.message };
        } finally {
            setLoading(false);
        }
    }, []);

    const logout = useCallback(async () => {
        setLoading(true);

        try {
            await authService.logout();
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            await sessionManager.clearSession();
            setUser(null);
            setError(null);
            setLoading(false);
        }
    }, []);

    const refreshUser = useCallback(async () => {
        await hydrateUser();
    }, [hydrateUser]);

    const clearError = useCallback(() => {
        setError(null);
    }, []);

    const value = {
        // State
        user,
        loading,
        error,
        isAuthenticated: !!user,

        // Actions
        login,
        logout,
        refreshUser,
        clearError,

        // Utilities
        hydrateUser,
    };

    return (
        <AuthenticationContext.Provider value={value}>
            {children}
        </AuthenticationContext.Provider>
    );
}

// Hook
export function useAuth() {
    const context = useContext(AuthenticationContext);
    if (context === undefined) {
        throw new Error('useAuth must be used within an AuthenticationProvider');
    }
    return context;
}

// Export classes for testing
export { AuthenticationService, SessionManager };
