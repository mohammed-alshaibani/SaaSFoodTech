import axios from 'axios';

// Debug: Log API URL configuration (only in development)
// Force Mock Mode for standalone demo in test branch
const isMockMode = process.env.NEXT_PUBLIC_API_MODE === 'test' || true;
const API_BASE_URL = isMockMode ? '/' : (process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api');

if (process.env.NODE_ENV === 'development') {
    console.log(`[API] Base URL: ${API_BASE_URL} ${isMockMode ? '(MOCK MODE)' : ''}`);
}

const api = axios.create({
    baseURL: API_BASE_URL,
    withCredentials: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Cache-Control': 'no-cache, no-store, must-revalidate',
        'Pragma': 'no-cache',
        'Expires': '0',
    },
    timeout: 30000,
});

/**
 * Handle Mock Routing for Test Branch
 */
if (isMockMode) {
    api.interceptors.request.use((config) => {
        const cleanPath = config.url.startsWith('/') ? config.url : `/${config.url}`;

        // Dynamic /me based on mock login
        if (cleanPath === '/me') {
            const mockRole = typeof window !== 'undefined' ? localStorage.getItem('mock_role') : null;
            const roleFile = mockRole ? `${mockRole}_me.json` : 'me.json';
            config.url = `/api/${roleFile}`;
        } else {
            config.url = `/api${cleanPath}.json`;
        }

        config.method = 'GET';
        console.log(`[Mock API] Intercepted: ${cleanPath} -> ${config.url}`);
        return config;
    });
}

// ─── Token cache ─────────────────────────────────────────────────────────────
// We keep an in-memory token reference to avoid a round-trip to /api/session
// on every request after the first successful hydration.
let cachedToken = null;

/**
 * Fetch the Bearer token from the Next.js session route handler.
 * The actual token lives in an httpOnly cookie that JS cannot read directly,
 * but the /api/session route handler CAN read it and return it to us.
 */
async function getToken() {
    if (cachedToken) return cachedToken;

    try {
        const res = await fetch('/api/session', { credentials: 'same-origin' });
        if (!res.ok) {
            cachedToken = null;
            return null;
        }
        const data = await res.json();
        cachedToken = data.token ?? null;
        return cachedToken;
    } catch {
        return null;
    }
}

/**
 * Clear the cached token — called on logout.
 * Import and call this from AuthContext.
 */
export function clearTokenCache() {
    cachedToken = null;
}

// ─── Request interceptor: attach Bearer token + guarantee Content-Type ───────
api.interceptors.request.use(async (config) => {
    const token = await getToken();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    // Guarantee Content-Type for state-transition PATCH requests (accept, complete, etc.)
    // Without a body, some browsers drop the Content-Type header causing 415.
    if (['patch', 'put', 'post'].includes(config.method)) {
        config.headers['Content-Type'] = 'application/json';
        if (config.data === undefined || config.data === null) {
            config.data = {};
        }
    }
    return config;
});

// ─── Response interceptor: handle errors globally ───────────────────────────
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        // Log error details in development
        if (process.env.NODE_ENV === 'development') {
            console.error('[API Error]', {
                url: error.config?.url,
                method: error.config?.method,
                status: error.response?.status,
                data: error.response?.data,
                message: error.message,
            });
        }

        if (error.response?.status === 401) {
            // Token expired or revoked — clear cache, clear server cookie
            cachedToken = null;
            await fetch('/api/session', { method: 'DELETE' });

            if (typeof window !== 'undefined') {
                const pathname = window.location.pathname;
                // Only force redirect to login if we are in the dashboard
                if (pathname.startsWith('/dashboard')) {
                    window.location.href = '/login';
                }
            }
        }

        if (error.response?.status === 403) {
            // Permission changed or revoked — trigger user data refresh
            if (typeof window !== 'undefined') {
                window.dispatchEvent(new CustomEvent('permission-changed'));
            }
        }

        if (error.response?.status === 404) {
            console.error(`[API] Endpoint not found: ${error.config?.url}`);
        }

        if (error.code === 'ECONNABORTED') {
            console.error('[API] Request timeout');
        }

        if (!error.response && error.request) {
            console.error('[API] Network error - check if backend is running at', API_BASE_URL);
        }

        return Promise.reject(error);
    }
);

export default api;
