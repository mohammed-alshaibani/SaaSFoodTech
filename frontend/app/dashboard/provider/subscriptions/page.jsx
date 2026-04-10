'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import {
    Crown,
    CheckCircle2,
    Clock,
    AlertCircle,
    TrendingUp,
    CreditCard,
    Calendar,
    ArrowUpRight,
    Shield,
    Zap,
    Users,
    Package,
    X,
    Plus,
    Edit2,
    Trash2
} from 'lucide-react';

export default function ProviderSubscriptionsPage() {
    const { user } = useAuth();
    const { t, language } = useI18n();
    const isRTL = language === 'ar';
    
    const [subscriptions, setSubscriptions] = useState([]);
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState(null);
    const [showModal, setShowModal] = useState(false);
    const [editingSubscription, setEditingSubscription] = useState(null);
    const [formData, setFormData] = useState({ customer_id: '', plan_id: '', amount: '', status: 'pending' });

    const fetchSubscriptions = useCallback(async () => {
        try {
            const res = await api.get('/provider/subscriptions');
            setSubscriptions(res.data.data || []);
        } catch (err) {
            console.error('Failed to fetch subscriptions:', err);
            setMessage({ type: 'error', text: t('subscriptions.fetchError') || 'Failed to load subscriptions' });
        }
    }, [t]);

    const fetchStats = useCallback(async () => {
        try {
            const res = await api.get('/provider/stats');
            setStats(res.data.data || null);
        } catch (err) {
            console.error('Failed to fetch stats:', err);
        }
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (editingSubscription) {
                await api.patch(`/provider/subscriptions/${editingSubscription.id}`, formData);
                setMessage({ type: 'success', text: t('subscriptions.updateSuccess') || 'Subscription updated successfully' });
            } else {
                await api.post('/provider/subscriptions', formData);
                setMessage({ type: 'success', text: t('subscriptions.createSuccess') || 'Subscription created successfully' });
            }
            setShowModal(false);
            setEditingSubscription(null);
            setFormData({ customer_id: '', plan_id: '', amount: '', status: 'pending' });
            fetchSubscriptions();
        } catch (err) {
            console.error('Failed to save subscription:', err);
            setMessage({ type: 'error', text: t('subscriptions.saveFailed') || 'Failed to save subscription' });
        }
    };

    const handleEdit = (subscription) => {
        setEditingSubscription(subscription);
        setFormData({ 
            customer_id: subscription.customer_id || '', 
            plan_id: subscription.plan_id || '', 
            amount: subscription.amount || '', 
            status: subscription.status || 'pending' 
        });
        setShowModal(true);
    };

    const handleDelete = async (id) => {
        if (!confirm(t('subscriptions.confirmDelete') || 'Are you sure you want to delete this subscription?')) return;
        try {
            await api.delete(`/provider/subscriptions/${id}`);
            setMessage({ type: 'success', text: t('subscriptions.deleteSuccess') || 'Subscription deleted successfully' });
            fetchSubscriptions();
        } catch (err) {
            console.error('Failed to delete subscription:', err);
            setMessage({ type: 'error', text: t('subscriptions.deleteFailed') || 'Failed to delete subscription' });
        }
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingSubscription(null);
        setFormData({ customer_id: '', plan_id: '', amount: '', status: 'pending' });
    };

    useEffect(() => {
        const loadData = async () => {
            setLoading(true);
            await Promise.all([fetchSubscriptions(), fetchStats()]);
            setLoading(false);
        };
        loadData();
    }, [fetchSubscriptions, fetchStats]);

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
            active: { color: 'bg-emerald-100 text-emerald-700 border-emerald-200', label: t('subscriptions.active') || 'Active' },
            pending: { color: 'bg-amber-100 text-amber-700 border-amber-200', label: t('subscriptions.pending') || 'Pending' },
            expired: { color: 'bg-red-100 text-red-700 border-red-200', label: t('subscriptions.expired') || 'Expired' },
            cancelled: { color: 'bg-gray-100 text-gray-700 border-gray-200', label: t('subscriptions.cancelled') || 'Cancelled' },
        };
        const configItem = config[status] || config.pending;
        
        return (
            <span className={`px-3 py-1 rounded-full text-xs font-bold border ${configItem.color}`}>
                {configItem.label}
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
                            {t('subscriptions.title') || 'Subscriptions'}
                        </h1>
                        <p className="text-gray-500 mt-1">
                            {t('subscriptions.subtitle') || 'Manage customer subscriptions and plans'}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button onClick={() => setShowModal(true)} className="flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-colors shadow-lg shadow-blue-200">
                            <Plus size={18} />
                            {t('subscriptions.addSubscription') || 'Add Subscription'}
                        </button>
                        <div className="px-4 py-2 bg-blue-50 text-blue-700 rounded-xl font-bold border border-blue-200">
                            <Crown size={18} className="inline-block mr-2" />
                            {t('subscriptions.providerPlan') || 'Provider Plan'}
                        </div>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard 
                        icon={Users} 
                        title={t('subscriptions.totalCustomers') || 'Total Customers'} 
                        value={stats?.total_customers || subscriptions.length || 0} 
                        color="blue" 
                    />
                    <StatCard 
                        icon={CheckCircle2} 
                        title={t('subscriptions.active') || 'Active'} 
                        value={stats?.active_subscriptions || subscriptions.filter(s => s.status === 'active').length || 0} 
                        color="emerald" 
                    />
                    <StatCard 
                        icon={Clock} 
                        title={t('subscriptions.pending') || 'Pending'} 
                        value={stats?.pending_subscriptions || subscriptions.filter(s => s.status === 'pending').length || 0} 
                        color="amber" 
                    />
                    <StatCard 
                        icon={TrendingUp} 
                        title={t('subscriptions.monthlyRevenue') || 'Monthly Revenue'} 
                        value={`$${stats?.monthly_revenue?.toFixed(2) || '0.00'}`} 
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

                {/* Subscriptions Table */}
                <div className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    <div className="p-6 border-b border-gray-100">
                        <h2 className="text-xl font-bold text-gray-900">
                            {t('subscriptions.customerSubscriptions') || 'Customer Subscriptions'}
                        </h2>
                    </div>
                    
                    {subscriptions.length === 0 ? (
                        <div className="p-12 text-center">
                            <div className="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <Package size={32} className="text-gray-400" />
                            </div>
                            <h3 className="text-lg font-bold text-gray-900 mb-2">
                                {t('subscriptions.noSubscriptions') || 'No subscriptions yet'}
                            </h3>
                            <p className="text-gray-500">
                                {t('subscriptions.noSubscriptionsDesc') || 'Customer subscriptions will appear here'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('subscriptions.customer') || 'Customer'}
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('subscriptions.plan') || 'Plan'}
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('subscriptions.status') || 'Status'}
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('subscriptions.startDate') || 'Start Date'}
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('subscriptions.endDate') || 'End Date'}
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('subscriptions.amount') || 'Amount'}
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                            {t('subscriptions.actions') || 'Actions'}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {subscriptions.map((subscription) => (
                                        <tr key={subscription.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center text-blue-600 font-bold">
                                                        {subscription.customer_name?.charAt(0) || 'C'}
                                                    </div>
                                                    <div>
                                                        <p className="font-bold text-gray-900">{subscription.customer_name || 'Unknown'}</p>
                                                        <p className="text-sm text-gray-500">{subscription.customer_email}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="inline-flex items-center px-3 py-1 rounded-lg text-sm font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                    {subscription.plan_name || subscription.plan || 'Basic'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <StatusBadge status={subscription.status} />
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-600">
                                                {subscription.starts_at ? new Date(subscription.starts_at).toLocaleDateString() : '-'}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-600">
                                                {subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString() : '-'}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="font-bold text-gray-900">
                                                    ${subscription.amount?.toFixed(2) || '0.00'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex gap-2 justify-end">
                                                    <button onClick={() => handleEdit(subscription)} className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                                        <Edit2 size={16} />
                                                    </button>
                                                    <button onClick={() => handleDelete(subscription.id)} className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                                        <Trash2 size={16} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Features Section */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200">
                        <div className="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center text-white mb-4">
                            <Shield size={24} />
                        </div>
                        <h3 className="text-lg font-bold text-gray-900 mb-2">
                            {t('subscriptions.securePayments') || 'Secure Payments'}
                        </h3>
                        <p className="text-gray-600 text-sm">
                            {t('subscriptions.securePaymentsDesc') || 'All transactions are encrypted and secure'}
                        </p>
                    </div>
                    
                    <div className="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-2xl p-6 border border-emerald-200">
                        <div className="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center text-white mb-4">
                            <Zap size={24} />
                        </div>
                        <h3 className="text-lg font-bold text-gray-900 mb-2">
                            {t('subscriptions.instantActivation') || 'Instant Activation'}
                        </h3>
                        <p className="text-gray-600 text-sm">
                            {t('subscriptions.instantActivationDesc') || 'Subscriptions activate immediately after payment'}
                        </p>
                    </div>
                    
                    <div className="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl p-6 border border-purple-200">
                        <div className="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center text-white mb-4">
                            <CreditCard size={24} />
                        </div>
                        <h3 className="text-lg font-bold text-gray-900 mb-2">
                            {t('subscriptions.flexibleBilling') || 'Flexible Billing'}
                        </h3>
                        <p className="text-gray-600 text-sm">
                            {t('subscriptions.flexibleBillingDesc') || 'Monthly or annual billing options available'}
                        </p>
                    </div>
                </div>

                {/* Modal */}
                {showModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-3xl p-8 w-full max-w-md shadow-2xl">
                            <div className="flex items-center justify-between mb-6">
                                <h2 className="text-2xl font-black text-gray-900">
                                    {editingSubscription ? (t('subscriptions.edit') || 'Edit Subscription') : (t('subscriptions.addSubscription') || 'Add Subscription')}
                                </h2>
                                <button onClick={closeModal} className="p-2 hover:bg-gray-100 rounded-xl transition">
                                    <X size={20} />
                                </button>
                            </div>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('subscriptions.customer') || 'Customer'}</label>
                                    <input
                                        type="text"
                                        value={formData.customer_id}
                                        onChange={(e) => setFormData({ ...formData, customer_id: e.target.value })}
                                        placeholder={t('subscriptions.customerId') || 'Customer ID'}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('subscriptions.plan') || 'Plan'}</label>
                                    <select
                                        value={formData.plan_id}
                                        onChange={(e) => setFormData({ ...formData, plan_id: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                                        required
                                    >
                                        <option value="">{t('subscriptions.selectPlan') || 'Select Plan'}</option>
                                        <option value="free">{t('subscriptions.free') || 'Free'}</option>
                                        <option value="premium">{t('subscriptions.premium') || 'Premium'}</option>
                                        <option value="enterprise">{t('subscriptions.enterprise') || 'Enterprise'}</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('subscriptions.amount') || 'Amount'}</label>
                                    <input
                                        type="number"
                                        value={formData.amount}
                                        onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                                        placeholder="0.00"
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">{t('subscriptions.status') || 'Status'}</label>
                                    <select
                                        value={formData.status}
                                        onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                                        className="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                                    >
                                        <option value="pending">{t('subscriptions.pending') || 'Pending'}</option>
                                        <option value="active">{t('subscriptions.active') || 'Active'}</option>
                                        <option value="expired">{t('subscriptions.expired') || 'Expired'}</option>
                                        <option value="cancelled">{t('subscriptions.cancelled') || 'Cancelled'}</option>
                                    </select>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button type="button" onClick={closeModal} className="flex-1 px-6 py-3 bg-gray-100 rounded-full text-sm font-bold text-gray-600 hover:bg-gray-200 transition">
                                        {t('subscriptions.cancel') || 'Cancel'}
                                    </button>
                                    <button type="submit" className="flex-1 px-6 py-3 bg-blue-600 rounded-full text-sm font-bold text-white hover:bg-blue-700 transition">
                                        {editingSubscription ? (t('subscriptions.update') || 'Update') : (t('subscriptions.create') || 'Create')}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
