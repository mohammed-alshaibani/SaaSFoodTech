import axios from 'axios';

const api = axios.create({
    baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api',
    withCredentials: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

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

// ─── Request interceptor: attach Bearer token ─────────────────────────────────
api.interceptors.request.use(async (config) => {
    const token = await getToken();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// ─── Response interceptor: handle 401 globally ────────────────────────────────
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response?.status === 401) {
            // Token expired or revoked — clear cache, clear server cookie, go to login
            cachedToken = null;
            await fetch('/api/session', { method: 'DELETE' });

            if (typeof window !== 'undefined') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export default api;
