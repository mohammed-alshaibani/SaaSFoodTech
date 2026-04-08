'use client';

import { useEffect, useRef } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Lazily initialises a single Laravel Echo instance per browser session.
 * Uses Reverb as the WebSocket driver via the Pusher-compatible client.
 *
 * The module-level singleton means we only create one WS connection even
 * if multiple components call useEcho() simultaneously.
 */
let echoInstance = null;

function getEcho() {
    if (echoInstance) return echoInstance;

    // Make Pusher available globally (required by laravel-echo internals)
    if (typeof window !== 'undefined') {
        window.Pusher = Pusher;
    }

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: process.env.NEXT_PUBLIC_REVERB_APP_KEY ?? 'foodtech-reverb-key',
        wsHost: process.env.NEXT_PUBLIC_REVERB_HOST ?? 'localhost',
        wsPort: parseInt(process.env.NEXT_PUBLIC_REVERB_PORT ?? '8080'),
        wssPort: parseInt(process.env.NEXT_PUBLIC_REVERB_PORT ?? '8080'),
        forceTLS: (process.env.NEXT_PUBLIC_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],

        // Supply the Sanctum token for private channel auth
        authEndpoint: `${process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api'}/broadcasting/auth`,
        auth: {
            headers: {
                // The token is read from the cookie by the Next.js /api/session route handler,
                // but we also need to pass it here for the Reverb auth handshake.
                Authorization: typeof window !== 'undefined'
                    ? `Bearer ${window.__reverb_token ?? ''}`
                    : '',
            },
        },
    });

    return echoInstance;
}

/**
 * useEcho(channelName, eventName, callback, deps)
 *
 * Subscribes to a private Reverb channel and listens for a specific event.
 * Automatically cleans up the listener when the component unmounts or deps change.
 *
 * @param {string}   channelName  - e.g. 'user.42' or 'service-requests'
 * @param {string}   eventName    - e.g. 'ServiceRequestUpdated'
 * @param {Function} callback     - called with the event payload
 * @param {Array}    deps         - React dependency array (re-subscribes on change)
 *
 * Usage:
 *   useEcho(`user.${user.id}`, 'ServiceRequestUpdated', (data) => {
 *       setRequests(prev => prev.map(r => r.id === data.request.id ? data.request : r));
 *   }, [user.id]);
 */
export function useEcho(channelName, eventName, callback, deps = []) {
    const callbackRef = useRef(callback);

    // Keep ref up-to-date so we never need to re-subscribe just because the callback changed
    useEffect(() => { callbackRef.current = callback; }, [callback]);

    useEffect(() => {
        // Only run inside the browser
        if (typeof window === 'undefined' || !channelName) return;

        const echo = getEcho();
        const channel = echo.private(channelName);

        channel.listen(`.${eventName}`, (e) => callbackRef.current(e));

        return () => {
            channel.stopListening(`.${eventName}`);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [channelName, eventName, ...deps]);
}

/**
 * setEchoToken(token)
 *
 * Must be called after login to ensure the Reverb auth header is correct.
 * Called by AuthContext after a successful login.
 */
export function setEchoToken(token) {
    if (typeof window !== 'undefined') {
        window.__reverb_token = token;
    }
    // Reset singleton so the next getEcho() re-creates with fresh auth
    if (echoInstance) {
        echoInstance.disconnect();
        echoInstance = null;
    }
}
