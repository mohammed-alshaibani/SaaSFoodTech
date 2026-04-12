'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useEcho } from '@/hooks/useEcho';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import { format } from 'date-fns';
import {
    Search,
    Filter,
    MapPin,
    Clock,
    Star,
    TrendingUp,
    RefreshCcw,
    Navigation,
    LayoutGrid,
    CheckCircle2,
    Calendar,
    ChevronDown,
    Map
} from 'lucide-react';

const StatusBadge = ({ status, t }) => {
    const config = {
        pending: 'bg-amber-50 text-amber-600 border-amber-200',
        accepted: 'bg-[#7C3AED]/10 text-[#7C3AED] border-[#7C3AED]/20',
        completed: 'bg-emerald-50 text-emerald-600 border-emerald-200',
    };
    return (
        <span className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border ${config[status] || 'bg-gray-100 text-gray-500 border-gray-200'}`}>
            {t(`dashboard.${status}`)}
        </span>
    );
};

export default function ProviderDashboard() {
    const { user } = useAuth();
    const { t, isRTL } = useI18n();

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
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white/50 pb-6 border-b border-gray-100">
                    <div className="flex items-center gap-5">
                        <div className="w-14 h-14 bg-gradient-to-tr from-[#7C3AED] to-purple-400 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-500/20">
                            <Map size={28} />
                        </div>
                        <div>
                            <h1 className="text-3xl font-black text-[#1A202C] tracking-tight">{t('dashboard.providerTitle')}</h1>
                            <p className="text-gray-500 font-medium mt-1">{t('dashboard.description')}</p>
                        </div>
                    </div>
                    <button
                        onClick={() => fetchRequests()}
                        className="flex items-center justify-center gap-2 px-6 py-3 bg-white border-2 border-[#7C3AED] rounded-full text-sm font-bold text-[#7C3AED] hover:bg-[#7C3AED] hover:text-white transition-all shadow-sm"
                    >
                        <RefreshCcw size={18} /> {t('dashboard.refresh')}
                    </button>
                </div>

                {/* Tabs Area */}
                <div className="flex flex-col md:flex-row items-center gap-6">
                    <div className="flex gap-1 p-1.5 bg-gray-100 border border-gray-200 rounded-xl">
                        <button
                            onClick={() => setTab('all')}
                            className={`px-6 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all ${tab === 'all' ? 'bg-[#7C3AED] text-white shadow-md shadow-purple-500/20' : 'text-gray-600 hover:text-gray-900'}`}
                        >
                            {t('dashboard.allRequests')}
                        </button>
                        <button
                            onClick={() => setTab('nearby')}
                            className={`px-6 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all ${tab === 'nearby' ? 'bg-[#7C3AED] text-white shadow-md shadow-purple-500/20' : 'text-gray-600 hover:text-gray-900'}`}
                        >
                            {t('dashboard.nearbyOrders')}
                        </button>
                    </div>

                    {tab === 'nearby' && (
                        <div className="flex flex-col gap-2">
                            <div className="flex items-center gap-4 bg-[#7C3AED]/10 border border-[#7C3AED]/20 px-6 py-3 rounded-xl animate-in slide-in-from-left-4">
                                <span className="text-[10px] font-bold text-[#7C3AED] uppercase tracking-wider">{t('dashboard.radius')}</span>
                                <select
                                    value={radius}
                                    onChange={e => setRadius(parseInt(e.target.value))}
                                    className="bg-transparent border-none text-[#1A202C] text-sm font-bold outline-none cursor-pointer"
                                >
                                    {[5, 10, 20, 50, 100].map(r => <option key={r} value={r}>{r} KM</option>)}
                                </select>
                                <div className="w-px h-6 bg-[#7C3AED]/20" />
                                <button onClick={requestGeo} className="flex items-center gap-2 text-[10px] font-bold text-[#7C3AED] uppercase tracking-wider hover:text-[#1A202C] transition">
                                    <Navigation size={14} /> Update
                                </button>
                            </div>
                            {/* Location status */}
                            <div className={`flex items-center gap-2 px-4 py-2 rounded-lg text-[10px] font-bold border ${coords && !geoError ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                    : geoError ? 'bg-amber-50 text-amber-700 border-amber-200'
                                        : 'bg-gray-50 text-gray-500 border-gray-200'
                                }`}>
                                <MapPin size={11} />
                                {coords && !geoError
                                    ? `GPS: ${coords.lat.toFixed(4)}, ${coords.lng.toFixed(4)} — ${radius} km radius`
                                    : geoError
                                        ? `${geoError} — ${coords ? `${coords.lat.toFixed(4)}, ${coords.lng.toFixed(4)}` : 'No coords'}`
                                        : 'Detecting location...'}
                            </div>
                        </div>
                    )}
                </div>

                {/* Filters */}
                <div className="flex flex-col lg:flex-row gap-4">
                    <div className="flex-1 relative">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
                        <input
                            type="text"
                            placeholder={t('dashboard.searchPlaceholder')}
                            value={searchTerm}
                            onChange={e => setSearchTerm(e.target.value)}
                            className="w-full bg-white border border-gray-200 rounded-xl pl-12 pr-4 py-3 text-sm font-bold text-[#1A202C] outline-none focus:ring-2 focus:ring-[#7C3AED] focus:border-[#7C3AED] transition shadow-sm"
                        />
                    </div>
                    <button
                        onClick={() => setShowFilters(!showFilters)}
                        className="px-6 py-3 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 hover:text-[#7C3AED] hover:border-[#7C3AED]/30 transition flex items-center gap-2 shadow-sm"
                    >
                        <Filter size={18} /> {t('dashboard.filters')} <ChevronDown size={18} className={`transition-transform ${showFilters ? 'rotate-180' : ''}`} />
                    </button>
                </div>

                {showFilters && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 bg-white border border-gray-200 rounded-3xl animate-in fade-in slide-in-from-top-4 shadow-sm">
                        <div className="space-y-3">
                            <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">STATUS</label>
                            <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)} className="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold text-[#1A202C] outline-none focus:ring-2 focus:ring-[#7C3AED] focus:border-[#7C3AED]">
                                <option value="all">All</option>
                                <option value="pending">Pending</option>
                                <option value="accepted">Accepted</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div className="space-y-3">
                            <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">SORT BY</label>
                            <select value={sortBy} onChange={e => setSortBy(e.target.value)} className="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold text-[#1A202C] outline-none focus:ring-2 focus:ring-[#7C3AED] focus:border-[#7C3AED]">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="nearest">Distance</option>
                            </select>
                        </div>
                    </div>
                )}

                {/* Grid of Cards */}
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
                            <div key={req.id} className="bg-white border border-gray-200 p-6 rounded-3xl shadow-[0_4px_20px_rgb(0,0,0,0.03)] hover:shadow-[0_8px_30px_rgb(124,58,237,0.08)] hover:border-[#7C3AED]/30 transition-all flex flex-col h-full group">
                                <div className="flex justify-between items-start gap-4 mb-4">
                                    <div className="flex-1 min-w-0">
                                        <h3 className="text-lg font-black text-[#1A202C] group-hover:text-[#7C3AED] transition-colors truncate">{req.title}</h3>
                                        <div className="mt-2">
                                            <StatusBadge status={req.status} t={t} />
                                        </div>
                                    </div>
                                    {req.distance_km != null && (
                                        <div className="bg-[#7C3AED]/10 border border-[#7C3AED]/20 px-3 py-1.5 rounded-lg flex items-center gap-1.5 whitespace-nowrap">
                                            <MapPin size={12} className="text-[#7C3AED]" />
                                            <span className="text-[10px] font-bold text-[#7C3AED]">{req.distance_km.toFixed(1)} KM</span>
                                        </div>
                                    )}
                                </div>

                                <p className="text-gray-500 font-medium text-sm leading-relaxed mb-6 flex-1 line-clamp-4">{req.description}</p>

                                <div className="space-y-3 mb-6">
                                    <div className="flex items-center gap-3 text-gray-500">
                                        <Calendar size={14} className="text-[#7C3AED]" />
                                        <span className="text-[10px] font-bold uppercase tracking-wider">{req.created_at ? format(new Date(req.created_at), 'yyyy/MM/dd') : 'N/A'}</span>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center text-gray-600 font-black">
                                            {req.customer?.name?.[0]?.toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="text-[8px] font-bold text-gray-400 uppercase tracking-wider">{t('dashboard.customer')}</p>
                                            <p className="text-xs font-bold text-[#1A202C]">{req.customer?.name ?? 'Unknown'}</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="pt-4 border-t border-gray-100">
                                    {req.status === 'pending' && (
                                        <button
                                            onClick={() => handleAccept(req.id)}
                                            disabled={accepting === req.id}
                                            className="w-full py-3 bg-[#7C3AED] hover:bg-purple-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-md shadow-purple-500/20 transition-all active:scale-[0.98] disabled:opacity-50"
                                        >
                                            {accepting === req.id ? '...PROCESSING' : t('dashboard.acceptRequest')}
                                        </button>
                                    )}
                                    {req.status === 'accepted' && req.provider?.id === user.id && (
                                        <button
                                            onClick={() => handleComplete(req.id)}
                                            disabled={completing === req.id}
                                            className="w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-md shadow-emerald-500/20 transition-all active:scale-[0.98] disabled:opacity-50"
                                        >
                                            {completing === req.id ? '...FINISHING' : t('dashboard.markCompleted')}
                                        </button>
                                    )}
                                    {req.status === 'accepted' && req.provider?.id !== user.id && (
                                        <div className="w-full text-center py-3 bg-gray-100 rounded-xl text-xs font-bold text-gray-500 uppercase tracking-wider border border-gray-200 italic">
                                            {t('dashboard.acceptedByOther')}
                                        </div>
                                    )}
                                    {req.status === 'completed' && (
                                        <div className="w-full flex items-center justify-center gap-3 py-3 bg-emerald-50 rounded-xl text-xs font-bold text-emerald-600 uppercase tracking-wider border border-emerald-200">
                                            <CheckCircle2 size={16} /> Order Completed
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {message && (
                    <div className={`fixed bottom-10 right-10 z-[60] px-6 py-4 rounded-2xl text-sm font-bold shadow-xl animate-in slide-in-from-bottom-5 ${message.type === 'error' ? 'bg-red-500 text-white' : 'bg-[#7C3AED] text-white'}`}>
                        {message.text}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
