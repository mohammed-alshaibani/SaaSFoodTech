'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';

// ─── Upgrade Banner ─────────────────────────────────────────────────────────
function UpgradeBanner({ requestCount, freeLimit }) {
    return (
        <div className="p-4 bg-amber-50 border border-amber-200 rounded-lg flex items-center justify-between gap-4">
            <div>
                <p className="font-semibold text-amber-800">Free Plan Limit Reached</p>
                <p className="text-sm text-amber-600">
                    You have used {requestCount}/{freeLimit} requests on the free plan.
                    Contact an admin to upgrade to Paid.
                </p>
            </div>
            <span className="shrink-0 text-2xl">🔒</span>
        </div>
    );
}

// ─── Status Badge ───────────────────────────────────────────────────────────
function StatusBadge({ status }) {
    const colors = {
        pending: 'bg-yellow-100 text-yellow-800',
        accepted: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
    };
    return (
        <span className={`px-2 py-1 rounded-full text-xs font-semibold ${colors[status] ?? 'bg-gray-100 text-gray-600'}`}>
            {status}
        </span>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────
export default function CustomerDashboard() {
    const { user, refreshUser } = useAuth();

    const [requests, setRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [formData, setFormData] = useState({
        title: '',
        description: '',
        latitude: 24.7136,  // Default to Riyadh
        longitude: 46.6753,
    });
    const [message, setMessage] = useState({ text: '', type: 'info' });
    const [enhancing, setEnhancing] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    // subscription gate data comes from /me (via AuthContext user object)
    const limitReached = user?.limit_reached ?? false;
    const requestCount = user?.request_count ?? 0;
    const freeLimit = user?.free_limit ?? 3;

    // ── Fetch requests ────────────────────────────────────────
    const fetchRequests = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/requests');
            setRequests(res.data.data ?? []);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    }, []); // no dependencies — always fetches all (scoped by backend)

    useEffect(() => {
        fetchRequests();
    }, [fetchRequests]);

    // ── AI Enhance ────────────────────────────────────────────
    const handleEnhance = async () => {
        if (!formData.title || !formData.description) return;
        setEnhancing(true);
        setMessage({ text: '✨ Enhancing description...', type: 'info' });
        try {
            const res = await api.post('/ai/enhance', {
                title: formData.title,
                description: formData.description,
            });
            setFormData(prev => ({ ...prev, description: res.data.enhanced_description }));
            const wasEnhanced = res.data.was_enhanced;
            setMessage({
                text: wasEnhanced ? '✅ Description enhanced!' : '⚠️ AI returned the original (check your API key).',
                type: wasEnhanced ? 'success' : 'warning',
            });
        } catch (err) {
            setMessage({ text: '❌ AI enhancement failed. You can still submit.', type: 'error' });
        } finally {
            setEnhancing(false);
        }
    };

    // ── Submit new request ────────────────────────────────────
    const handleSubmit = async (e) => {
        e.preventDefault();
        if (limitReached) return; // double-guard — button is also disabled
        setSubmitting(true);
        setMessage({ text: '', type: 'info' });
        try {
            await api.post('/requests', formData);
            setShowForm(false);
            setFormData({ title: '', description: '', latitude: 24.7136, longitude: 46.6753 });
            await fetchRequests();
            await refreshUser(); // re-sync request_count + limit_reached from /api/me
            setMessage({ text: '✅ Request created successfully!', type: 'success' });
        } catch (err) {
            const msg = err.response?.data?.message || 'Failed to create request.';
            setMessage({ text: msg, type: 'error' });
        } finally {
            setSubmitting(false);
        }
    };

    // ── Message styles ────────────────────────────────────────
    const msgStyles = {
        info: 'bg-blue-50 text-blue-700 border-blue-200',
        success: 'bg-green-50 text-green-700 border-green-200',
        warning: 'bg-amber-50 text-amber-700 border-amber-200',
        error: 'bg-red-50 text-red-700 border-red-200',
    };

    // ── Render ────────────────────────────────────────────────
    return (
        <div className="space-y-6">

            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h2 className="text-2xl font-semibold">My Service Requests</h2>
                    {user?.plan === 'free' && (
                        <p className="text-sm text-gray-400 mt-0.5">
                            {requestCount} / {freeLimit} requests used (Free Plan)
                        </p>
                    )}
                </div>
                <button
                    onClick={() => setShowForm(v => !v)}
                    disabled={limitReached}
                    className={`px-4 py-2 rounded-md text-white transition text-sm font-medium
                        ${limitReached
                            ? 'bg-gray-300 cursor-not-allowed'
                            : 'bg-blue-600 hover:bg-blue-700'}`}
                >
                    {showForm ? 'Cancel' : '+ New Request'}
                </button>
            </div>

            {/* Upgrade banner */}
            {limitReached && (
                <UpgradeBanner requestCount={requestCount} freeLimit={freeLimit} />
            )}

            {/* Toast message */}
            {message.text && (
                <div className={`p-3 border rounded-md text-sm ${msgStyles[message.type]}`}>
                    {message.text}
                </div>
            )}

            {/* Create form */}
            {showForm && !limitReached && (
                <div className="bg-white p-6 rounded-lg shadow-sm border space-y-4">
                    <h3 className="text-lg font-medium">Create New Request</h3>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {/* Title */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input
                                type="text"
                                value={formData.title}
                                onChange={e => setFormData(p => ({ ...p, title: e.target.value }))}
                                className="w-full rounded-md border-gray-300 border p-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm"
                                required
                            />
                        </div>

                        {/* Description + AI enhance */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea
                                value={formData.description}
                                onChange={e => setFormData(p => ({ ...p, description: e.target.value }))}
                                className="w-full rounded-md border-gray-300 border p-2 h-28 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm"
                                required
                            />
                            <div className="flex justify-end mt-1">
                                <button
                                    type="button"
                                    onClick={handleEnhance}
                                    disabled={enhancing || !formData.title || !formData.description}
                                    className="text-xs bg-purple-100 text-purple-700 px-3 py-1 rounded hover:bg-purple-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {enhancing ? '⏳ Enhancing...' : '✨ Enhance with AI'}
                                </button>
                            </div>
                        </div>

                        {/* Location */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                                <input
                                    type="number" step="any"
                                    value={formData.latitude}
                                    onChange={e => setFormData(p => ({ ...p, latitude: parseFloat(e.target.value) }))}
                                    className="w-full rounded-md border p-2 text-sm border-gray-300"
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                                <input
                                    type="number" step="any"
                                    value={formData.longitude}
                                    onChange={e => setFormData(p => ({ ...p, longitude: parseFloat(e.target.value) }))}
                                    className="w-full rounded-md border p-2 text-sm border-gray-300"
                                    required
                                />
                            </div>
                        </div>

                        <button
                            type="submit"
                            disabled={submitting}
                            className="w-full bg-blue-600 text-white rounded-md py-2 hover:bg-blue-700 transition disabled:opacity-60 text-sm font-medium"
                        >
                            {submitting ? 'Submitting...' : 'Submit Request'}
                        </button>
                    </form>
                </div>
            )}

            {/* Requests table */}
            <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            {['Title', 'Status', 'Provider', 'Date'].map(h => (
                                <th key={h} className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {loading ? (
                            <tr><td colSpan={4} className="px-6 py-8 text-center text-gray-400">Loading...</td></tr>
                        ) : requests.length === 0 ? (
                            <tr><td colSpan={4} className="px-6 py-8 text-center text-gray-400">No requests yet. Create your first one!</td></tr>
                        ) : (
                            requests.map(req => (
                                <tr key={req.id} className="hover:bg-gray-50 transition">
                                    <td className="px-6 py-4 font-medium text-gray-900">{req.title}</td>
                                    <td className="px-6 py-4"><StatusBadge status={req.status} /></td>
                                    <td className="px-6 py-4 text-gray-500">{req.provider?.name ?? <span className="italic text-gray-300">Searching...</span>}</td>
                                    <td className="px-6 py-4 text-gray-500">{new Date(req.created_at).toLocaleDateString()}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
