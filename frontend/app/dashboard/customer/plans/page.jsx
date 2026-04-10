'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import { CreditCard, Check, Star, Zap, Building2, Loader2 } from 'lucide-react';


export default function PlansPage() {
    const { user, refreshUser } = useAuth();
    const { t, isRTL } = useI18n();
    const [loading, setLoading] = useState(true);
    const [plansLoading, setPlansLoading] = useState(true);
    const [message, setMessage] = useState(null);
    const [currentPlan, setCurrentPlan] = useState(user?.plan || 'free');
    const [plans, setPlans] = useState([]);

    useEffect(() => {
        setCurrentPlan(user?.plan || 'free');
    }, [user?.plan]);

    useEffect(() => {
        const fetchPlans = async () => {
            try {
                const res = await api.get('/subscription/plans');
                setPlans(res.data.data || []);
            } catch (err) {
                console.error('Failed to fetch plans:', err);
                setMessage({ type: 'error', text: t('plans.fetchError') || 'Failed to load plans' });
            } finally {
                setPlansLoading(false);
            }
        };
        fetchPlans();
    }, [t]);

    const handleUpgrade = async (planId) => {
        if (planId === currentPlan) return;

        setLoading(true);
        setMessage(null);

        try {
            const res = await api.post('/subscription/upgrade', {
                plan: planId,
                payment_method: 'credit_card', // Default payment method (required by backend)
            });
            setMessage({ type: 'success', text: res.data.message || t('plans.upgradeSuccess') });
            await refreshUser();
        } catch (err) {
            const errorMsg = err.response?.data?.message || err.response?.data?.error || t('plans.upgradeError');
            setMessage({ type: 'error', text: errorMsg });
        } finally {
            setLoading(false);
        }
    };

    const getColorClasses = (color, isCurrent) => {
        const colors = {
            gray: isCurrent
                ? 'border-gray-400 bg-gray-50'
                : 'border-gray-200 hover:border-gray-300',
            purple: isCurrent
                ? 'border-[#7C3AED] bg-[#7C3AED]/5'
                : 'border-[#7C3AED] hover:border-[#6D28D9] hover:shadow-[#7C3AED]/10',
            blue: isCurrent
                ? 'border-blue-500 bg-blue-50'
                : 'border-blue-400 hover:border-blue-500 hover:shadow-blue-500/10',
        };
        return colors[color] || colors.gray;
    };

    return (
        <DashboardLayout>
            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black text-[#1E293B]">
                            {isRTL ? 'خطط الاشتراك' : 'Subscription Plans'}
                        </h1>
                        <p className="text-gray-500 mt-1">
                            {isRTL
                                ? 'اختر الخطة المناسبة لاحتياجاتك'
                                : 'Choose the plan that fits your needs'}
                        </p>
                    </div>
                    {currentPlan && (
                        <div className="px-4 py-2 bg-[#7C3AED]/10 text-[#7C3AED] rounded-full text-sm font-bold capitalize">
                            {isRTL ? 'الخطة الحالية: ' : 'Current Plan: '} {currentPlan}
                        </div>
                    )}
                </div>

                {/* Message */}
                {message && (
                    <div
                        className={`p-4 rounded-2xl text-sm font-bold ${
                            message.type === 'success'
                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                : 'bg-red-50 text-red-700 border border-red-200'
                        }`}
                    >
                        {message.text}
                    </div>
                )}

                {/* Plans Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {plansLoading ? (
                        [1, 2, 3].map(i => (
                            <div key={i} className="bg-white rounded-3xl border-2 border-gray-100 p-8 h-[500px] animate-pulse" />
                        ))
                    ) : plans.length === 0 ? (
                        <div className="col-span-full flex flex-col items-center justify-center p-20 text-gray-400 gap-4">
                            <CreditCard size={64} className="opacity-10" />
                            <p className="font-bold uppercase tracking-wider">{t('plans.noPlans') || 'No plans available'}</p>
                        </div>
                    ) : (
                        plans.map((plan) => {
                            const Icon = plan.name === 'free' ? Star : plan.name === 'premium' ? Zap : Building2;
                            const color = plan.name === 'free' ? 'gray' : plan.name === 'premium' ? 'purple' : 'blue';
                            const isCurrent = currentPlan === plan.name;
                            const colorClasses = getColorClasses(color, isCurrent);
                            const planId = plan.name;
                            const planPrice = plan.price;
                            const planFeatures = Array.isArray(plan.features) ? plan.features : [];
                            const billingCycle = plan.billing_cycle === 'monthly' ? 'month' : 'year';

                            return (
                                <div
                                    key={plan.id}
                                    className={`relative bg-white rounded-3xl border-2 p-8 transition-all duration-300 ${colorClasses} ${
                                        plan.popular && !isCurrent ? 'shadow-xl shadow-[#7C3AED]/10 scale-105' : ''
                                    } ${isCurrent ? '' : 'hover:shadow-lg'}`}
                                >
                                {/* Popular Badge */}
                                {plan.popular && !isCurrent && (
                                    <div className="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1 bg-[#7C3AED] text-white text-xs font-black rounded-full">
                                        {isRTL ? 'الأكثر شيوعاً' : 'Most Popular'}
                                    </div>
                                )}

                                {/* Current Badge */}
                                {isCurrent && (
                                    <div className="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1 bg-gray-600 text-white text-xs font-black rounded-full">
                                        {isRTL ? 'الخطة الحالية' : 'Current Plan'}
                                    </div>
                                )}

                                {/* Icon */}
                                <div
                                    className={`w-14 h-14 rounded-2xl flex items-center justify-center mb-6 ${
                                        color === 'purple'
                                            ? 'bg-[#7C3AED]/10 text-[#7C3AED]'
                                            : color === 'blue'
                                            ? 'bg-blue-100 text-blue-600'
                                            : 'bg-gray-100 text-gray-600'
                                    }`}
                                >
                                    <Icon size={28} />
                                </div>

                                {/* Plan Name */}
                                <h3 className="text-xl font-black text-[#1E293B] mb-2">
                                    {isRTL ? plan.display_name : plan.name}
                                </h3>

                                {/* Price */}
                                <div className="mb-6">
                                    <span className="text-4xl font-black text-[#1E293B]">
                                        {planPrice}
                                    </span>
                                    <span className="text-gray-500 text-sm font-medium">
                                        {' '}
                                        SAR/{isRTL ? (billingCycle === 'month' ? 'شهر' : 'سنة') : billingCycle}
                                    </span>
                                </div>

                                {/* Features */}
                                <ul className="space-y-3 mb-8">
                                    {planFeatures.map((feature, idx) => (
                                        <li key={idx} className="flex items-center gap-3 text-sm text-gray-600">
                                            <div className="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                                <Check size={12} strokeWidth={3} />
                                            </div>
                                            {feature}
                                        </li>
                                    ))
                                </ul>

                                {/* Action Button */}
                                <button
                                    onClick={() => handleUpgrade(planId)}
                                    disabled={isCurrent || loading}
                                    className={`w-full py-4 rounded-2xl font-bold text-sm transition-all ${
                                        isCurrent
                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                            : color === 'purple'
                                            ? 'bg-[#7C3AED] text-white hover:bg-[#6D28D9] shadow-lg shadow-[#7C3AED]/25'
                                            : color === 'blue'
                                            ? 'bg-blue-500 text-white hover:bg-blue-600 shadow-lg shadow-blue-500/25'
                                            : 'bg-gray-900 text-white hover:bg-gray-800'
                                    }`}
                                >
                                    {loading ? (
                                        <Loader2 className="animate-spin mx-auto" size={20} />
                                    ) : isCurrent ? (
                                        isRTL ? 'الخطة الحالية' : 'Current Plan'
                                    ) : (
                                        isRTL ? 'اختر الخطة' : 'Select Plan'
                                    )}
                                </button>
                            </div>
                        );
                        })
                    )}
                </div>

                {/* Usage Stats */}
                <div className="bg-white rounded-3xl border border-gray-100 p-8">
                    <h2 className="text-xl font-black text-[#1E293B] mb-6">
                        {isRTL ? 'استخدامك الحالي' : 'Your Usage'}
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div className="p-6 bg-gray-50 rounded-2xl">
                            <p className="text-sm text-gray-500 font-medium mb-1">
                                {isRTL ? 'الطلبات هذا الشهر' : 'Requests This Month'}
                            </p>
                            <p className="text-3xl font-black text-[#1E293B]">
                                {user?.request_count ?? 0}
                            </p>
                        </div>
                        <div className="p-6 bg-gray-50 rounded-2xl">
                            <p className="text-sm text-gray-500 font-medium mb-1">
                                {isRTL ? 'الحد المجاني' : 'Free Limit'}
                            </p>
                            <p className="text-3xl font-black text-[#1E293B]">{user?.free_limit ?? 3}</p>
                        </div>
                        <div className="p-6 bg-gray-50 rounded-2xl">
                            <p className="text-sm text-gray-500 font-medium mb-1">
                                {isRTL ? 'المتبقي' : 'Remaining'}
                            </p>
                            <p className={`text-3xl font-black ${user?.limit_reached ? 'text-red-500' : 'text-emerald-500'}`}>
                                {Math.max(0, (user?.free_limit ?? 3) - (user?.request_count ?? 0))}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
