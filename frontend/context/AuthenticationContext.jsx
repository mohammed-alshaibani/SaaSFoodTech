'use client';

import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api, { clearTokenCache } from '@/lib/api';

const AuthenticationContext = createContext(undefined);

export function AuthenticationProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const hydrateUser = useCallback(async () => {
        try {
            const response = await api.get('/me');
            const userData = response.data.data || response.data;
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
                const response = await fetch('/api/session');
                if (response.ok) {
                    await hydrateUser();
                }
            } catch (error) {
                setError(error.message);
            } finally {
                setLoading(false);
            }
        };

        restore();

        // Listen for permission changes (403 responses)
        const handlePermissionChange = async () => {
            await hydrateUser();
        };

        if (typeof window !== 'undefined') {
            window.addEventListener('permission-changed', handlePermissionChange);
        }

        return () => {
            if (typeof window !== 'undefined') {
                window.removeEventListener('permission-changed', handlePermissionChange);
            }
        };
    }, [hydrateUser]);

    const login = useCallback(async (credentials) => {
        setLoading(true);
        setError(null);

        // --- MOCK LOGIN FOR TEST BRANCH ---
        if (process.env.NEXT_PUBLIC_API_MODE === 'test') {
            let mockUser = null;
            let mockRole = 'customer';

            if (credentials.email === 'admin@test.com') {
                mockRole = 'admin';
                mockUser = { id: 99, name: 'المدير العام', email: credentials.email, roles: ['admin'] };
            } else if (credentials.email === 'provider@test.com') {
                mockRole = 'provider_admin';
                mockUser = { id: 50, name: 'مختبر نجد', email: credentials.email, roles: ['provider_admin'] };
            } else if (credentials.email === 'customer@test.com') {
                mockRole = 'customer';
                mockUser = { id: 10, name: 'مطعم النبلاء', email: credentials.email, roles: ['customer'] };
            }

            if (mockUser) {
                if (typeof window !== 'undefined') {
                    localStorage.setItem('mock_role', mockRole);
                }

                // Simulate session creation
                await fetch('/api/session', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token: 'mock-token', role: mockRole }),
                });

                setUser(mockUser);
                setLoading(false);
                return { success: true, user: mockUser };
            }
            // Fallback if fake email doesn't match
            setError('Invalid mock credentials (Try: admin@quality.sa)');
            setLoading(false);
            return { success: false, error: 'Invalid mock credentials' };
        }

        try {
            const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
            const response = await fetch(`${baseUrl}/login`, {
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

            const { access_token, user: userData } = data;

            // Normalize role - handle both string and object formats
            let role = 'customer';
            if (userData.roles && userData.roles.length > 0) {
                const firstRole = userData.roles[0];
                role = typeof firstRole === 'string' ? firstRole : firstRole.name;
            }

            await fetch('/api/session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: access_token, role }),
            });

            setUser(userData);
            return { success: true, user: userData };
        } catch (error) {
            setError(error.message);
            return { success: false, error: error.message };
        } finally {
            setLoading(false);
        }
    }, []);

    const register = useCallback(async (formData) => {
        setLoading(true);
        setError(null);

        try {
            const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
            const response = await fetch(`${baseUrl}/register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(formData),
            });

            const data = await response.json();

            if (!response.ok) {
                // Return data so component can handle validation errors
                return { success: false, error: data.message || 'Registration failed', errors: data.errors };
            }

            const { access_token, user: userData } = data;

            // Normalize role - handle both string and object formats
            let role = 'customer';
            if (userData.roles && userData.roles.length > 0) {
                const firstRole = userData.roles[0];
                role = typeof firstRole === 'string' ? firstRole : firstRole.name;
            }

            await fetch('/api/session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: access_token, role }),
            });

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
            await api.post('/logout');
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            await fetch('/api/session', { method: 'DELETE' });
            clearTokenCache();
            if (typeof window !== 'undefined') {
                localStorage.removeItem('mock_role');
            }
            setUser(null);
            setError(null);
            setLoading(false);

            // Force redirect to login and clear any client-side state
            if (typeof window !== 'undefined') {
                window.location.href = '/login';
            }
        }
    }, []);

    const refreshUser = useCallback(async () => {
        await hydrateUser();
    }, [hydrateUser]);

    const clearError = useCallback(() => {
        setError(null);
    }, []);

    const value = {
        user,
        loading,
        error,
        isAuthenticated: !!user,
        login,
        register,
        logout,
        refreshUser,
        clearError,
        hydrateUser,
    };

    return (
        <AuthenticationContext.Provider value={value}>
            {children}
        </AuthenticationContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthenticationContext);
    if (context === undefined) {
        throw new Error('useAuth must be used within an AuthenticationProvider');
    }
    return context;
}

