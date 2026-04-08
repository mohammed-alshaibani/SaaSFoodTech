'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useEcho } from '@/hooks/useEcho';
import { Search, Filter, Plus, MapPin, Clock, Star, TrendingUp } from 'lucide-react';

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
    const [filteredRequests, setFilteredRequests] = useState([]);
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

    // Enhanced filtering
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [sortBy, setSortBy] = useState('newest');
    const [showFilters, setShowFilters] = useState(false);
    const [stats, setStats] = useState({ total: 0, pending: 0, accepted: 0, completed: 0 });

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

    // Filtering and sorting logic
    useEffect(() => {
        let filtered = [...requests];

        // Search filter
        if (searchTerm) {
            filtered = filtered.filter(req => 
                req.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                req.description.toLowerCase().includes(searchTerm.toLowerCase()) ||
                req.provider?.name?.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }

        // Status filter
        if (statusFilter !== 'all') {
            filtered = filtered.filter(req => req.status === statusFilter);
        }

        // Sorting
        filtered.sort((a, b) => {
            switch (sortBy) {
                case 'newest':
                    return new Date(b.created_at) - new Date(a.created_at);
                case 'oldest':
                    return new Date(a.created_at) - new Date(b.created_at);
                case 'title':
                    return a.title.localeCompare(b.title);
                default:
                    return 0;
            }
        });

        setFilteredRequests(filtered);

        // Update stats
        const newStats = {
            total: requests.length,
            pending: requests.filter(r => r.status === 'pending').length,
            accepted: requests.filter(r => r.status === 'accepted').length,
            completed: requests.filter(r => r.status === 'completed').length,
        };
        setStats(newStats);
    }, [requests, searchTerm, statusFilter, sortBy]);

    // Real-time updates via Reverb
    useEcho(
        user ? `user.${user.id}` : null,
        'ServiceRequestUpdated',
        useCallback((data) => {
            const updated = data.request;
            if (!updated) return;

            setRequests(prev => {
                const idx = prev.findIndex(r => r.id === updated.id);
                if (idx === -1) {
                    return [updated, ...prev];
                }
                const next = [...prev];
                next[idx] = { ...next[idx], ...updated };
                return next;
            });

            const actionLabels = { accepted: 'Accepted', completed: 'Completed', created: 'New request' };
            setMessage({
                text: `${actionLabels[data.action] ?? 'Updated'}: "${updated.title}"`,
                type: data.action === 'completed' ? 'success' : 'info'
            });
            setTimeout(() => setMessage({ text: '', type: 'info' }), 3000);
        }, []),
        [user?.id]
    );

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
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 p-6 md:p-10">

            {/* Page header */}
            <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                        Customer Dashboard
                    </h1>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage your service requests and track their progress.
                    </p>
                </div>
                <button
                    onClick={() => setShowForm(v => !v)}
                    disabled={limitReached}
                    className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition
                        ${limitReached
                            ? 'bg-slate-300 text-slate-500 cursor-not-allowed'
                            : 'bg-indigo-600 hover:bg-indigo-700 text-white'}`}
                >
                    <Plus className="h-4 w-4" />
                    {showForm ? 'Cancel' : 'New Request'}
                </button>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Requests</p>
                            <p className="text-2xl font-bold text-slate-900 dark:text-slate-100">{stats.total}</p>
                        </div>
                        <TrendingUp className="h-8 w-8 text-indigo-500" />
                    </div>
                </div>
                <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-slate-500 dark:text-slate-400">Pending</p>
                            <p className="text-2xl font-bold text-amber-600">{stats.pending}</p>
                        </div>
                        <Clock className="h-8 w-8 text-amber-500" />
                    </div>
                </div>
                <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-slate-500 dark:text-slate-400">In Progress</p>
                            <p className="text-2xl font-bold text-blue-600">{stats.accepted}</p>
                        </div>
                        <Star className="h-8 w-8 text-blue-500" />
                    </div>
                </div>
                <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-slate-500 dark:text-slate-400">Completed</p>
                            <p className="text-2xl font-bold text-emerald-600">{stats.completed}</p>
                        </div>
                        <div className="h-8 w-8 bg-emerald-100 rounded-full flex items-center justify-center">
                            <span className="text-emerald-600 font-bold">C</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Upgrade banner */}
            {limitReached && (
                <UpgradeBanner requestCount={requestCount} freeLimit={freeLimit} />
            )}

            {/* Toast message */}
            {message.text && (
                <div className={`fixed top-5 end-5 z-50 px-4 py-3 rounded-xl border shadow-lg text-sm font-medium transition-all ${msgStyles[message.type]}`}>
                    {message.text}
                </div>
            )}

            {/* Enhanced Search and Filters */}
            <div className="mb-6 flex flex-col lg:flex-row gap-4">
                {/* Search Bar */}
                <div className="flex-1 relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                    <input
                        type="text"
                        placeholder="Search your requests..."
                        value={searchTerm}
                        onChange={e => setSearchTerm(e.target.value)}
                        className="w-full pl-10 pr-4 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                {/* Filter Toggle */}
                <button
                    onClick={() => setShowFilters(!showFilters)}
                    className="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                >
                    <Filter className="h-4 w-4" />
                    Filters
                    {(statusFilter !== 'all' || sortBy !== 'newest') && (
                        <span className="w-2 h-2 bg-indigo-600 rounded-full"></span>
                    )}
                </button>
            </div>

            {/* Expanded Filters */}
            {showFilters && (
                <div className="mb-6 p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-100 dark:border-slate-800">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {/* Status Filter */}
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">Status</label>
                            <select
                                value={statusFilter}
                                onChange={e => setStatusFilter(e.target.value)}
                                className="w-full text-sm border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="all">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="accepted">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>

                        {/* Sort By */}
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">Sort By</label>
                            <select
                                value={sortBy}
                                onChange={e => setSortBy(e.target.value)}
                                className="w-full text-sm border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="title">Title A-Z</option>
                            </select>
                        </div>

                        {/* Clear Filters */}
                        <div className="flex items-end">
                            <button
                                onClick={() => {
                                    setSearchTerm('');
                                    setStatusFilter('all');
                                    setSortBy('newest');
                                }}
                                className="w-full text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 font-medium"
                            >
                                Clear Filters
                            </button>
                        </div>
                    </div>
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

            {/* Requests List */}
            <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800 overflow-hidden">
                {loading ? (
                    <div className="flex items-center justify-center py-24 text-slate-400">
                        <div className="animate-spin rounded-full h-10 w-10 border-4 border-indigo-500 border-t-transparent" />
                    </div>
                ) : filteredRequests.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-24 text-slate-400 gap-3">
                        <span className="text-5xl">Folder</span>
                        <p className="text-sm">
                            {searchTerm || statusFilter !== 'all'
                                ? 'No requests match your filters.'
                                : 'No requests yet. Create your first one!'}
                        </p>
                    </div>
                ) : (
                    <div className="divide-y divide-slate-100 dark:divide-slate-800">
                        {filteredRequests.map(req => (
                            <div key={req.id} className="p-6 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex-1 min-w-0">
                                        <h3 className="font-semibold text-slate-900 dark:text-slate-100 mb-2">{req.title}</h3>
                                        <p className="text-sm text-slate-600 dark:text-slate-400 mb-3 line-clamp-2">{req.description}</p>
                                        <div className="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                                            <span className="flex items-center gap-1">
                                                <Clock className="h-3 w-3" />
                                                {new Date(req.created_at).toLocaleDateString()}
                                            </span>
                                            <span className="flex items-center gap-1">
                                                <MapPin className="h-3 w-3" />
                                                {req.latitude?.toFixed(2)}, {req.longitude?.toFixed(2)}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex flex-col items-end gap-2">
                                        <StatusBadge status={req.status} />
                                        {req.provider?.name && (
                                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                                Provider: {req.provider.name}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Footer */}
            {!loading && filteredRequests.length > 0 && (
                <p className="mt-6 text-xs text-slate-400 text-end">
                    {filteredRequests.length} request{filteredRequests.length !== 1 ? 's' : ''} shown
                    {searchTerm && ` (filtered by "${searchTerm}")`}
                    {statusFilter !== 'all' && ` (${statusFilter})`}
                </p>
            )}
        </div>
    );
}
