'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import { format } from 'date-fns';
import {
    ClipboardList,
    Search,
    Filter,
    CheckCircle2,
    AlertCircle,
    Clock,
    MapPin,
    User,
    Calendar,
    ArrowUpRight,
    Star,
    TrendingUp,
    MoreVertical,
    Eye,
    CheckCircle,
    XCircle,
    MessageSquare,
    FileText,
    Package,
    Zap,
    Shield,
    Bell
} from 'lucide-react';

export default function ProviderRequestsPage() {
    const { user } = useAuth();
    const { t, language } = useI18n();
    const isRTL = language === 'ar';

    const [requests, setRequests] = useState([]);
    const [filteredRequests, setFilteredRequests] = useState([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [filterStatus, setFilterStatus] = useState('all');
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState(null);
    const [stats, setStats] = useState(null);

    const fetchRequests = useCallback(async () => {
        try {
            // Fetch standard requests (own accepted/completed) and nearby discovering requests
            const lat = user?.latitude || 24.7136; // Default to Riyadh if user lacks coords
            const lng = user?.longitude || 46.6753;
            const radius = 50; // 50km search radius

            const [reqsRes, nearbyRes] = await Promise.all([
                api.get('/requests'),
                api.get(`/requests/nearby?latitude=${lat}&longitude=${lng}&radius=${radius}`)
            ]);

            const standardData = reqsRes.data.data || [];
            const nearbyData = nearbyRes.data.data || [];

            // Merge uniquely by ID
            const mergedMap = new Map();
            standardData.forEach(r => mergedMap.set(r.id, r));
            nearbyData.forEach(r => mergedMap.set(r.id, r));

            const requestData = Array.from(mergedMap.values());

            setRequests(requestData);
            setFilteredRequests(requestData);
        } catch (err) {
            console.error('Failed to fetch requests:', err);
            setMessage({ type: 'error', text: t('requests.fetchError') || 'Failed to load requests' });
        } finally {
            setLoading(false);
        }
    }, [t, user]);

    const fetchStats = useCallback(async () => {
        try {
            const res = await api.get('/provider/stats');
            setStats(res.data.data || null);
        } catch (err) {
            console.error('Failed to fetch stats:', err);
        }
    }, []);

    useEffect(() => {
        const loadData = async () => {
            setLoading(true);
            await Promise.all([fetchRequests(), fetchStats()]);
            setLoading(false);
        };
        loadData();
    }, [fetchRequests, fetchStats]);

    useEffect(() => {
        let result = requests;

        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            result = result.filter(r =>
                r.title?.toLowerCase().includes(query) ||
                r.description?.toLowerCase().includes(query) ||
                r.customer?.name?.toLowerCase().includes(query)
            );
        }

        if (filterStatus !== 'all') {
            result = result.filter(r => r.status === filterStatus);
        }

        setFilteredRequests(result);
    }, [searchQuery, filterStatus, requests]);

    const handleAccept = async (requestId) => {
        try {
            await api.patch(`/requests/${requestId}/accept`, { _action: 'accept' });
            setMessage({ type: 'success', text: t('requests.accepted') || 'Request accepted' });
            fetchRequests();
        } catch (err) {
            setMessage({ type: 'error', text: err.response?.data?.message || t('requests.acceptError') || 'Failed to accept request' });
        }
    };

    const handleComplete = async (requestId) => {
        try {
            await api.patch(`/requests/${requestId}/complete`, { _action: 'complete' });
            setMessage({ type: 'success', text: t('requests.completed') || 'Request completed' });
            fetchRequests();
        } catch (err) {
            setMessage({ type: 'error', text: err.response?.data?.message || t('requests.completeError') || 'Failed to complete request' });
        }
    };

    const StatCard = ({ icon: Icon, title, value, color }) => {
        const colors = {
            blue: 'bg-blue-50 border-blue-200 text-blue-600',
            emerald: 'bg-emerald-50 border-emerald-200 text-emerald-600',
            amber: 'bg-amber-50 border-amber-200 text-amber-600',
            purple: 'bg-purple-50 border-purple-200 text-purple-600',
        };

        return (
            <div className={`p-6 rounded-2xl border ${colors[color]} backdrop-blur-sm`}>
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium opacity-80">{title}</p>
                        <p className="text-2xl font-black mt-1">{value}</p>
                    </div>
                    <div className="p-3 bg-white/50 rounded-xl">
                        <Icon size={24} />
                    </div>
                </div>
            </div>
        );
    };

    const StatusBadge = ({ status }) => {
        const config = {
            pending: { color: 'bg-amber-100 text-amber-700 border-amber-200', label: t('requests.pending') || 'Pending' },
            accepted: { color: 'bg-blue-100 text-blue-700 border-blue-200', label: t('requests.accepted') || 'Accepted' },
            completed: { color: 'bg-emerald-100 text-emerald-700 border-emerald-200', label: t('requests.completed') || 'Completed' },
            cancelled: { color: 'bg-red-100 text-red-700 border-red-200', label: t('requests.cancelled') || 'Cancelled' },
        };
        const configItem = config[status] || config.pending;

        return (
            <span className={`px-3 py-1 rounded-full text-xs font-bold border ${configItem.color}`}>
                {configItem.label}
            </span>
        );
    };

    const PriorityBadge = ({ priority }) => {
        const colors = {
            high: 'bg-red-100 text-red-700 border-red-200',
            medium: 'bg-amber-100 text-amber-700 border-amber-200',
            low: 'bg-emerald-100 text-emerald-700 border-emerald-200',
        };

        return (
            <span className={`px-3 py-1 rounded-full text-xs font-bold border ${colors[priority] || colors.medium}`}>
                {priority || 'medium'}
            </span>
        );
    };

    if (loading) {
        return (
            <DashboardLayout>
                <div className="flex items-center justify-center h-96">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </DashboardLayout>
        );
    }

    return (
        <DashboardLayout>
            <div className={`space-y-8 ${isRTL ? 'text-right' : 'text-left'}`}>
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black text-gray-900">
                            {t('requests.title') || 'Service Requests'}
                        </h1>
                        <p className="text-gray-500 mt-1">
                            {t('requests.subtitle') || 'Manage and respond to customer requests'}
                        </p>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        icon={ClipboardList}
                        title={t('requests.total') || 'Total Requests'}
                        value={requests.length}
                        color="blue"
                    />
                    <StatCard
                        icon={Clock}
                        title={t('requests.pending') || 'Pending'}
                        value={requests.filter(r => r.status === 'pending').length}
                        color="amber"
                    />
                    <StatCard
                        icon={CheckCircle2}
                        title={t('requests.completed') || 'Completed'}
                        value={requests.filter(r => r.status === 'completed').length}
                        color="emerald"
                    />
                    <StatCard
                        icon={TrendingUp}
                        title={t('requests.thisMonth') || 'This Month'}
                        value={requests.filter(r => {
                            const created = new Date(r.created_at);
                            const now = new Date();
                            return created.getMonth() === now.getMonth() && created.getFullYear() === now.getFullYear();
                        }).length}
                        color="purple"
                    />
                </div>

                {/* Message */}
                {message && (
                    <div className={`p-4 rounded-xl border ${message.type === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'}`}>
                        <div className="flex items-center gap-2">
                            {message.type === 'error' ? <AlertCircle size={20} /> : <CheckCircle2 size={20} />}
                            <span className="font-bold">{message.text}</span>
                        </div>
                    </div>
                )}

                {/* Filters */}
                <div className="flex flex-col md:flex-row gap-4">
                    <div className="flex-1 relative">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
                        <input
                            type="text"
                            placeholder={t('requests.search') || 'Search requests...'}
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full pl-12 pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>
                    <select
                        value={filterStatus}
                        onChange={(e) => setFilterStatus(e.target.value)}
                        className="px-4 py-3 bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="all">{t('requests.allStatuses') || 'All Statuses'}</option>
                        <option value="pending">{t('requests.pending') || 'Pending'}</option>
                        <option value="accepted">{t('requests.accepted') || 'Accepted'}</option>
                        <option value="completed">{t('requests.completed') || 'Completed'}</option>
                    </select>
                </div>

                {/* Requests List */}
                <div className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    {filteredRequests.length === 0 ? (
                        <div className="p-12 text-center">
                            <div className="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <Package size={32} className="text-gray-400" />
                            </div>
                            <h3 className="text-lg font-bold text-gray-900 mb-2">
                                {t('requests.noRequests') || 'No requests found'}
                            </h3>
                            <p className="text-gray-500">
                                {searchQuery ? t('requests.noSearchResults') || 'Try adjusting your search' : t('requests.noRequestsDesc') || 'Service requests will appear here'}
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {filteredRequests.map((request) => (
                                <div key={request.id} className="p-6 hover:bg-gray-50 transition-colors">
                                    <div className="flex flex-col lg:flex-row lg:items-start gap-4">
                                        {/* Request Info */}
                                        <div className="flex-1">
                                            <div className="flex items-start gap-3 mb-2">
                                                <h3 className="text-lg font-bold text-gray-900">{request.title || 'Untitled Request'}</h3>
                                                <StatusBadge status={request.status} />
                                                <PriorityBadge priority={request.priority} />
                                            </div>
                                            <p className="text-gray-600 text-sm mb-3 line-clamp-2">{request.description}</p>

                                            <div className="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                                                <div className="flex items-center gap-1">
                                                    <User size={14} />
                                                    <span>{request.customer?.name || 'Unknown Customer'}</span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <MapPin size={14} />
                                                    <span>{request.location || 'No location'}</span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <Calendar size={14} />
                                                    <span>{request.created_at ? format(new Date(request.created_at), 'MMM d, yyyy') : '-'}</span>
                                                </div>
                                                {request.budget && (
                                                    <div className="flex items-center gap-1">
                                                        <span className="font-bold text-gray-700">${request.budget}</span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Actions */}
                                        <div className="flex items-center gap-2">
                                            {request.status === 'pending' && (
                                                <button
                                                    onClick={() => handleAccept(request.id)}
                                                    className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-colors"
                                                >
                                                    <CheckCircle size={18} />
                                                    {t('requests.accept') || 'Accept'}
                                                </button>
                                            )}
                                            {request.status === 'accepted' && (
                                                <button
                                                    onClick={() => handleComplete(request.id)}
                                                    className="flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-colors"
                                                >
                                                    <CheckCircle2 size={18} />
                                                    {t('requests.complete') || 'Complete'}
                                                </button>
                                            )}
                                            <button className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-colors">
                                                <Eye size={20} />
                                            </button>
                                            <button className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-colors">
                                                <MessageSquare size={20} />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Features Section */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200">
                        <div className="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center text-white mb-4">
                            <Zap size={24} />
                        </div>
                        <h3 className="text-lg font-bold text-gray-900 mb-2">
                            {t('requests.fastResponse') || 'Fast Response'}
                        </h3>
                        <p className="text-gray-600 text-sm">
                            {t('requests.fastResponseDesc') || 'Respond to customer requests quickly and efficiently'}
                        </p>
                    </div>

                    <div className="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-2xl p-6 border border-emerald-200">
                        <div className="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center text-white mb-4">
                            <Bell size={24} />
                        </div>
                        <h3 className="text-lg font-bold text-gray-900 mb-2">
                            {t('requests.realTimeUpdates') || 'Real-time Updates'}
                        </h3>
                        <p className="text-gray-600 text-sm">
                            {t('requests.realTimeUpdatesDesc') || 'Get instant notifications for new requests'}
                        </p>
                    </div>

                    <div className="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl p-6 border border-purple-200">
                        <div className="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center text-white mb-4">
                            <Shield size={24} />
                        </div>
                        <h3 className="text-lg font-bold text-gray-900 mb-2">
                            {t('requests.secureMessaging') || 'Secure Messaging'}
                        </h3>
                        <p className="text-gray-600 text-sm">
                            {t('requests.secureMessagingDesc') || 'Communicate securely with customers'}
                        </p>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
