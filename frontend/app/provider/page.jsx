'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useEcho } from '@/hooks/useEcho';

// ─── Status Badge ─────────────────────────────────────────────────────────────
function StatusBadge({ status }) {
    const map = {
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        accepted: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        completed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    };
    return (
        <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${map[status] ?? 'bg-slate-100 text-slate-500'}`}>
            {status}
        </span>
    );
}

// ─── Distance pill ────────────────────────────────────────────────────────────
function DistancePill({ km }) {
    if (km == null) return null;
    return (
        <span className="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
            <span>📍</span>
            <span>{km} km away</span>
        </span>
    );
}

// ─── Request Card ─────────────────────────────────────────────────────────────
function RequestCard({ req, userId, onAccept, onComplete, accepting, completing }) {
    const isMine = req.provider?.id === userId;

    return (
        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow p-5 flex flex-col gap-4">

            {/* Header */}
            <div className="flex justify-between items-start gap-2">
                <h3 className="font-bold text-slate-900 dark:text-slate-100 leading-snug text-sm">{req.title}</h3>
                <StatusBadge status={req.status} />
            </div>

            {/* Description */}
            <p className="text-slate-500 dark:text-slate-400 text-xs leading-relaxed line-clamp-3">
                {req.description}
            </p>

            {/* Meta */}
            <div className="flex flex-wrap gap-x-4 gap-y-1">
                <DistancePill km={req.distance_km} />
                <span className="text-xs text-slate-400">👤 {req.customer?.name ?? '—'}</span>
                {req.provider?.name && (
                    <span className="text-xs text-slate-400">🔧 {req.provider.name}</span>
                )}
            </div>

            {/* Actions */}
            <div className="pt-3 border-t border-slate-100 dark:border-slate-800 flex gap-2">
                {req.status === 'pending' && (
                    <button
                        id={`accept-btn-${req.id}`}
                        onClick={() => onAccept(req.id)}
                        disabled={accepting === req.id}
                        className="flex-1 bg-indigo-600 hover:bg-indigo-700 active:scale-95 disabled:opacity-60
                                   text-white py-2 rounded-xl text-xs font-semibold transition-all"
                    >
                        {accepting === req.id ? 'Accepting…' : 'Accept Request'}
                    </button>
                )}
                {req.status === 'accepted' && isMine && (
                    <button
                        id={`complete-btn-${req.id}`}
                        onClick={() => onComplete(req.id)}
                        disabled={completing === req.id}
                        className="flex-1 bg-emerald-600 hover:bg-emerald-700 active:scale-95 disabled:opacity-60
                                   text-white py-2 rounded-xl text-xs font-semibold transition-all"
                    >
                        {completing === req.id ? 'Completing…' : 'Mark Completed'}
                    </button>
                )}
                {req.status === 'accepted' && !isMine && (
                    <span className="flex-1 text-center text-xs text-slate-400 py-2">
                        Accepted by another provider
                    </span>
                )}
                {req.status === 'completed' && (
                    <span className="flex-1 text-center text-xs text-emerald-600 font-semibold py-2">✓ Completed</span>
                )}
            </div>
        </div>
    );
}

// ─── Main Dashboard ───────────────────────────────────────────────────────────
export default function ProviderDashboard() {
    const { user } = useAuth();

    // ── State ─────────────────────────────────────────────────────────────────
    const [tab, setTab] = useState('all');          // 'all' | 'nearby'
    const [requests, setRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [radius, setRadius] = useState(20);
    const [coords, setCoords] = useState(null);         // null = not yet fetched
    const [geoError, setGeoError] = useState('');
    const [accepting, setAccepting] = useState(null);
    const [completing, setCompleting] = useState(null);
    const [toast, setToast] = useState(null);
    const pendingLiveUpdate = useRef(false);

    const showToast = useCallback((msg, type = 'info') => {
        setToast({ msg, type });
        setTimeout(() => setToast(null), 3500);
    }, []);

    // ── Geolocation ───────────────────────────────────────────────────────────
    const requestGeo = useCallback(() => {
        if (!navigator.geolocation) {
            setGeoError('Geolocation is not supported by your browser.');
            return;
        }
        navigator.geolocation.getCurrentPosition(
            pos => {
                setCoords({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                setGeoError('');
            },
            () => {
                // Fallback: Riyadh / Saudi Arabia centre
                setCoords({ lat: 24.7136, lng: 46.6753 });
                setGeoError('Could not get live location — using Riyadh as default.');
            }
        );
    }, []);

    useEffect(() => { requestGeo(); }, [requestGeo]);

    // ── Fetch ─────────────────────────────────────────────────────────────────
    const fetchRequests = useCallback(async (silent = false) => {
        if (!silent) setLoading(true);
        try {
            let res;
            if (tab === 'nearby' && coords) {
                res = await api.get('/requests/nearby', {
                    params: { latitude: coords.lat, longitude: coords.lng, radius },
                });
            } else {
                res = await api.get('/requests');
            }
            setRequests(res.data.data ?? []);
        } catch (err) {
            if (!silent) showToast(err.response?.data?.message ?? 'Failed to load requests.', 'error');
        } finally {
            if (!silent) setLoading(false);
        }
    }, [tab, coords, radius, showToast]);

    useEffect(() => { fetchRequests(); }, [fetchRequests]);

    // ── Real-time updates via Reverb ──────────────────────────────────────────
    useEcho(
        user ? `user.${user.id}` : null,
        'ServiceRequestUpdated',
        useCallback((data) => {
            const updated = data.request;
            if (!updated) return;

            // In-place update so we don't lose scroll position
            setRequests(prev => {
                const idx = prev.findIndex(r => r.id === updated.id);
                if (idx === -1) {
                    // New request arrived — prepend for 'all' tab
                    if (tab === 'all') return [updated, ...prev];
                    return prev;
                }
                const next = [...prev];
                next[idx] = { ...next[idx], ...updated };
                return next;
            });

            const actionLabels = { accepted: '✓ Accepted', completed: '✓ Completed', created: '🆕 New request' };
            showToast(`${actionLabels[data.action] ?? 'Updated'}: "${updated.title}"`, 'info');
        }, [tab, showToast]),
        [user?.id, tab]
    );

    // ── Actions ───────────────────────────────────────────────────────────────
    const handleAccept = useCallback(async (id) => {
        setAccepting(id);
        try {
            await api.patch(`/requests/${id}/accept`);
            showToast('Request accepted!', 'success');
            fetchRequests(true);
        } catch (err) {
            const msg = err.response?.data?.message ?? 'Failed to accept request.';
            showToast(msg, 'error');
        } finally {
            setAccepting(null);
        }
    }, [fetchRequests, showToast]);

    const handleComplete = useCallback(async (id) => {
        setCompleting(id);
        try {
            await api.patch(`/requests/${id}/complete`);
            showToast('Request marked as completed!', 'success');
            fetchRequests(true);
        } catch (err) {
            showToast(err.response?.data?.message ?? 'Failed to complete request.', 'error');
        } finally {
            setCompleting(null);
        }
    }, [fetchRequests, showToast]);

    // ── Toast styles ─────────────────────────────────────────────────────────
    const toastClass = {
        info: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
        success: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
        error: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800',
        warning: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
    };

    // ── Render ────────────────────────────────────────────────────────────────
    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 p-6 md:p-10">

            {/* Page header */}
            <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                        Provider Dashboard
                    </h1>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage and accept service requests in real-time.
                    </p>
                </div>
                <button
                    id="provider-refresh-btn"
                    onClick={() => fetchRequests()}
                    className="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 transition font-medium"
                >
                    ↻ Refresh
                </button>
            </div>

            {/* Toast */}
            {toast && (
                <div className={`fixed top-5 end-5 z-50 px-4 py-3 rounded-xl border shadow-lg text-sm font-medium transition-all ${toastClass[toast.type]}`}>
                    {toast.msg}
                </div>
            )}

            {/* Tabs */}
            <div className="flex gap-1 p-1 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 mb-6 w-fit">
                {[{ id: 'all', label: '📋 All Requests' }, { id: 'nearby', label: '📍 Nearby Orders' }].map(t => (
                    <button
                        key={t.id}
                        id={`tab-${t.id}`}
                        onClick={() => setTab(t.id)}
                        className={`px-5 py-2 rounded-lg text-sm font-medium transition-all ${tab === t.id
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
                            }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {/* Nearby controls */}
            {tab === 'nearby' && (
                <div className="mb-6 flex flex-wrap items-center gap-4 p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-100 dark:border-slate-800">
                    <div className="flex items-center gap-3">
                        <span className="text-sm font-medium text-slate-700 dark:text-slate-300">Radius:</span>
                        <select
                            id="nearby-radius"
                            value={radius}
                            onChange={e => setRadius(Number(e.target.value))}
                            className="text-sm border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-1.5 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            {[5, 10, 20, 50, 100].map(r => (
                                <option key={r} value={r}>{r} km</option>
                            ))}
                        </select>
                    </div>
                    <button
                        id="nearby-update-location-btn"
                        onClick={requestGeo}
                        className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                    >
                        🎯 Update my location
                    </button>
                    {coords && (
                        <span className="text-xs text-slate-400">
                            {coords.lat.toFixed(4)}, {coords.lng.toFixed(4)}
                        </span>
                    )}
                    {geoError && (
                        <span className={`text-xs ${geoError.includes('default') ? 'text-amber-600' : 'text-red-500'}`}>
                            ⚠ {geoError}
                        </span>
                    )}
                </div>
            )}

            {/* Content */}
            {loading ? (
                <div className="flex items-center justify-center py-24 text-slate-400">
                    <div className="animate-spin rounded-full h-10 w-10 border-4 border-indigo-500 border-t-transparent" />
                </div>
            ) : requests.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-24 text-slate-400 gap-3">
                    <span className="text-5xl">{tab === 'nearby' ? '📭' : '🗂️'}</span>
                    <p className="text-sm">
                        {tab === 'nearby'
                            ? `No pending requests within ${radius} km of your location.`
                            : 'No requests found.'}
                    </p>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    {requests.map(req => (
                        <RequestCard
                            key={req.id}
                            req={req}
                            userId={user?.id}
                            onAccept={handleAccept}
                            onComplete={handleComplete}
                            accepting={accepting}
                            completing={completing}
                        />
                    ))}
                </div>
            )}

            {/* Footer */}
            {!loading && requests.length > 0 && (
                <p className="mt-6 text-xs text-slate-400 text-end">
                    {requests.length} request{requests.length !== 1 ? 's' : ''} shown
                    {tab === 'nearby' && coords && ` within ${radius} km`}
                </p>
            )}
        </div>
    );
}
