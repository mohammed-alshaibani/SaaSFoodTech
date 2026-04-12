'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useEcho } from '@/hooks/useEcho';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import {
    Navigation,
    LayoutGrid,
    MapPin
} from 'lucide-react';

// Specialized Provider Components
import { ProviderHeader } from '@/components/provider/ProviderHeader';
import { RequestCard } from '@/components/provider/RequestCard';
import { ProviderFilters } from '@/components/provider/ProviderFilters';

export default function ProviderDashboard() {
    const { user } = useAuth();
    const { t } = useI18n();

    const [tab, setTab] = useState('all');
    const [requests, setRequests] = useState([]);
    const [filteredRequests, setFilteredRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [radius, setRadius] = useState(20);
    const [coords, setCoords] = useState(null);
    const [geoError, setGeoError] = useState('');
    const [accepting, setAccepting] = useState(null);
    const [completing, setCompleting] = useState(null);
    const [message, setMessage] = useState(null);

    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [sortBy, setSortBy] = useState('newest');
    const [showFilters, setShowFilters] = useState(false);

    const showMessage = useCallback((text, type = 'info') => {
        setMessage({ text, type });
        setTimeout(() => setMessage(null), 3500);
    }, []);

    const requestGeo = useCallback(() => {
        if (!navigator.geolocation) {
            setGeoError('Not supported');
            return;
        }
        navigator.geolocation.getCurrentPosition(
            pos => {
                setCoords({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                setGeoError('');
            },
            () => {
                setCoords({ lat: 24.7136, lng: 46.6753 });
                setGeoError('Default (Riyadh)');
            }
        );
    }, []);

    useEffect(() => { requestGeo(); }, [requestGeo]);

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
        } catch {
            if (!silent) showMessage('Failed to load', 'error');
        } finally {
            if (!silent) setLoading(false);
        }
    }, [tab, coords, radius, showMessage]);

    useEffect(() => { fetchRequests(); }, [fetchRequests]);

    useEffect(() => {
        let filtered = [...requests];
        if (searchTerm) {
            filtered = filtered.filter(req =>
                req.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                req.description.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }
        if (statusFilter !== 'all') {
            filtered = filtered.filter(req => req.status === statusFilter);
        }
        filtered.sort((a, b) => {
            switch (sortBy) {
                case 'newest': return new Date(b.created_at) - new Date(a.created_at);
                case 'oldest': return new Date(a.created_at) - new Date(b.created_at);
                case 'nearest': return (a.distance_km || Infinity) - (b.distance_km || Infinity);
                default: return 0;
            }
        });
        setFilteredRequests(filtered);
    }, [requests, searchTerm, statusFilter, sortBy]);

    useEcho(
        user ? `user.${user.id}` : null,
        'ServiceRequestUpdated',
        useCallback((data) => {
            const updated = data.request;
            if (!updated) return;
            setRequests(prev => {
                const idx = prev.findIndex(r => r.id === updated.id);
                if (idx === -1) return tab === 'all' ? [updated, ...prev] : prev;
                const next = [...prev];
                next[idx] = { ...next[idx], ...updated };
                return next;
            });
            showMessage(`${t(`dashboard.${data.action}`)}: "${updated.title}"`);
        }, [tab, t, showMessage]),
        [user?.id, tab]
    );

    const handleAccept = async (id) => {
        setAccepting(id);
        try {
            await api.patch(`/requests/${id}/accept`);
            showMessage('Accepted successfully!', 'success');
            fetchRequests(true);
        } catch (err) {
            showMessage(err.response?.data?.message || 'Error', 'error');
        } finally {
            setAccepting(null);
        }
    };

    const handleComplete = async (id) => {
        setCompleting(id);
        try {
            await api.patch(`/requests/${id}/complete`);
            showMessage('Order Completed!', 'success');
            fetchRequests(true);
        } catch {
            showMessage('Completion failed', 'error');
        } finally {
            setCompleting(null);
        }
    };

    return (
        <DashboardLayout>
            <div className="space-y-10">
                <ProviderHeader
                    title={t('dashboard.providerTitle') || 'Provider Dashboard'}
                    description={t('dashboard.description') || 'Manage and track your service requests.'}
                    onSync={() => fetchRequests()}
                    isSyncing={loading}
                    t={t}
                />

                <div className="flex flex-col md:flex-row items-center gap-6">
                    <div className="flex gap-1 p-1.5 bg-gray-100 border border-gray-200 rounded-xl">
                        {['all', 'nearby'].map(tKey => (
                            <button
                                key={tKey}
                                onClick={() => setTab(tKey)}
                                className={`px-6 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all ${tab === tKey ? 'bg-primary text-white shadow-md shadow-purple-500/20' : 'text-gray-600 hover:text-gray-900'}`}
                            >
                                {t(`dashboard.${tKey === 'all' ? 'allRequests' : 'nearbyOrders'}`)}
                            </button>
                        ))}
                    </div>

                    {tab === 'nearby' && (
                        <div className="flex flex-col gap-2">
                            <div className="flex items-center gap-4 bg-primary/10 border border-primary/20 px-6 py-3 rounded-xl animate-in slide-in-from-left-4">
                                <span className="text-[10px] font-bold text-primary uppercase tracking-wider">{t('dashboard.radius')}</span>
                                <select
                                    value={radius}
                                    onChange={e => setRadius(parseInt(e.target.value))}
                                    className="bg-transparent border-none text-charcoal text-sm font-bold outline-none cursor-pointer"
                                >
                                    {[5, 10, 20, 50, 100].map(r => <option key={r} value={r}>{r} KM</option>)}
                                </select>
                                <div className="w-px h-6 bg-primary/20" />
                                <button onClick={requestGeo} className="flex items-center gap-2 text-[10px] font-bold text-primary uppercase tracking-wider hover:text-charcoal transition">
                                    <Navigation size={14} /> Update
                                </button>
                            </div>
                            <div className={`flex items-center gap-2 px-4 py-2 rounded-lg text-[10px] font-bold border ${coords && !geoError ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : geoError ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-gray-50 text-gray-500 border-gray-200'}`}>
                                <MapPin size={11} />
                                {coords && !geoError ? `GPS: ${coords.lat.toFixed(4)}, ${coords.lng.toFixed(4)} — ${radius} km` : geoError ? geoError : 'Detecting...'}
                            </div>
                        </div>
                    )}
                </div>

                <ProviderFilters
                    searchTerm={searchTerm}
                    onSearchChange={setSearchTerm}
                    statusFilter={statusFilter}
                    onStatusFilterChange={setStatusFilter}
                    sortBy={sortBy}
                    onSortByChange={setSortBy}
                    showFilters={showFilters}
                    onToggleFilters={() => setShowFilters(!showFilters)}
                    t={t}
                />

                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    {loading ? (
                        [1, 2, 3, 4, 5, 6].map(i => <div key={i} className="h-[400px] bg-gray-50 border border-gray-100 rounded-3xl animate-pulse" />)
                    ) : filteredRequests.length === 0 ? (
                        <div className="col-span-full flex flex-col items-center justify-center p-20 text-gray-400 gap-4">
                            <LayoutGrid size={64} className="opacity-10" />
                            <p className="font-bold uppercase tracking-wider">{t('dashboard.noRequests')}</p>
                        </div>
                    ) : (
                        filteredRequests.map(req => (
                            <RequestCard
                                key={req.id}
                                req={req}
                                user={user}
                                t={t}
                                onAccept={handleAccept}
                                onComplete={handleComplete}
                                isAccepting={accepting === req.id}
                                isCompleting={completing === req.id}
                            />
                        ))
                    )}
                </div>

                {message && (
                    <div className={`fixed bottom-10 right-10 z-[60] px-6 py-4 rounded-2xl text-sm font-bold shadow-xl animate-in slide-in-from-bottom-5 ${message.type === 'error' ? 'bg-red-500 text-white' : 'bg-primary text-white'}`}>
                        {message.text}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
