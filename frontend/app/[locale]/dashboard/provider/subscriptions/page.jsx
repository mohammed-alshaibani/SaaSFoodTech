'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import { CreditCard, Check, Star, Zap, Building2, Loader2, Crown } from 'lucide-react';

export default function ProviderSubscriptionsPage() {
    const { user, refreshUser } = useAuth();
    const { t, isRTL } = useI18n();
    const [loading, setLoading] = useState(false);
    const [plansLoading, setPlansLoading] = useState(true);
    const [message, setMessage] = useState(null);
    const [currentPlan, setCurrentPlan] = useState(user?.plan || 'free');
    const [plans, setPlans] = useState([]);

    // Mock Payment Modal State
    const [showPaymentModal, setShowPaymentModal] = useState(false);
    const [selectedPlanForPayment, setSelectedPlanForPayment] = useState(null);

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
                setMessage({ type: 'error', text: 'Failed to load plans' });
            } finally {
                setPlansLoading(false);
            }
        };
        fetchPlans();
    }, []);

    const handleSelectPlan = (planId) => {
        if (planId === currentPlan) return;
        setSelectedPlanForPayment(planId);
        setShowPaymentModal(true);
    };

    const processMockPayment = async (e) => {
        e.preventDefault();
        setLoading(true);
        setMessage(null);

        try {
            const res = await api.post('/subscription/upgrade', {
                plan: selectedPlanForPayment,
                payment_method: 'card',
            });

            setMessage({ type: 'success', text: res.data.message || 'Plan updated successfully!' });
            await refreshUser();
            setShowPaymentModal(false);
        } catch (err) {
            const errorMsg = err.response?.data?.message || err.response?.data?.error || 'Upgrade failed';
            setMessage({ type: 'error', text: errorMsg });
            setShowPaymentModal(false);
        } finally {
            setLoading(false);
            setSelectedPlanForPayment(null);
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
                            {isRTL ? 'خطط الاشتراك' : 'My Subscription Plan'}
                        </h1>
                        <p className="text-gray-500 mt-1">
                            {isRTL
                                ? 'اختر الخطة المناسبة لنشاطك التجاري'
                                : 'Upgrade or change your provider plan at any time'}
                        </p>
                    </div>
                    {currentPlan && (
                        <div className="flex items-center gap-2 px-4 py-2 bg-[#7C3AED]/10 text-[#7C3AED] rounded-full text-sm font-bold capitalize">
                            <Crown size={16} />
                            {isRTL ? 'الخطة الحالية: ' : 'Current Plan: '} {currentPlan}
                        </div>
                    )}
                </div>

                {/* Message */}
                {message && (
                    <div
                        className={`p-4 rounded-2xl text-sm font-bold ${message.type === 'success'
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
                            <p className="font-bold uppercase tracking-wider">No plans available</p>
                        </div>
                    ) : (
                        plans.map((plan) => {
                            const Icon = plan.name === 'free' ? Star : plan.name === 'premium' ? Zap : Building2;
                            const color = plan.name === 'free' ? 'gray' : plan.name === 'premium' ? 'purple' : 'blue';
                            const isCurrent = currentPlan === plan.name;
                            const colorClasses = getColorClasses(color, isCurrent);
                            const planFeatures = Array.isArray(plan.features) ? plan.features : [];
                            const billingCycle = plan.billing_cycle === 'monthly' ? 'month' : 'year';

                            return (
                                <div
                                    key={plan.id}
                                    className={`relative bg-white rounded-3xl border-2 p-8 transition-all duration-300 ${colorClasses} ${plan.popular && !isCurrent ? 'shadow-xl shadow-[#7C3AED]/10 scale-105' : ''
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
                                            {isRTL ? 'خطتك الحالية' : 'Your Current Plan'}
                                        </div>
                                    )}

                                    {/* Icon */}
                                    <div
                                        className={`w-14 h-14 rounded-2xl flex items-center justify-center mb-6 ${color === 'purple'
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
                                            {plan.price}
                                        </span>
                                        <span className="text-gray-500 text-sm font-medium">
                                            {' '}SAR/{isRTL ? (billingCycle === 'month' ? 'شهر' : 'سنة') : billingCycle}
                                        </span>
                                    </div>

                                    {/* Features */}
                                    <ul className="space-y-3 mb-8">
                                        {planFeatures.map((feature, idx) => (
                                            <li key={idx} className="flex items-center gap-3 text-sm text-gray-600">
                                                <div className="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                                                    <Check size={12} strokeWidth={3} />
                                                </div>
                                                {feature}
                                            </li>
                                        ))}
                                    </ul>

                                    {/* Action Button */}
                                    <button
                                        onClick={() => handleSelectPlan(plan.name)}
                                        disabled={isCurrent || loading}
                                        className={`w-full py-4 rounded-2xl font-bold text-sm transition-all ${isCurrent
                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                            : color === 'purple'
                                                ? 'bg-[#7C3AED] text-white hover:bg-[#6D28D9] shadow-lg shadow-[#7C3AED]/25'
                                                : color === 'blue'
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600 shadow-lg shadow-blue-500/25'
                                                    : 'bg-gray-900 text-white hover:bg-gray-800'
                                            }`}
                                    >
                                        {loading && selectedPlanForPayment === plan.name ? (
                                            <Loader2 className="animate-spin mx-auto" size={20} />
                                        ) : isCurrent ? (
                                            isRTL ? 'خطتك الحالية' : 'Current Plan'
                                        ) : (
                                            isRTL ? 'اختر هذه الخطة' : 'Select Plan'
                                        )}
                                    </button>
                                </div>
                            );
                        })
                    )}
                </div>

                {/* Mock Payment Modal */}
                {showPaymentModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
                        <div className="bg-white rounded-3xl p-8 w-full max-w-md shadow-2xl">
                            <div className="text-center mb-6">
                                <div className="w-16 h-16 bg-[#7C3AED]/10 text-[#7C3AED] rounded-full flex items-center justify-center mx-auto mb-4">
                                    <CreditCard size={32} />
                                </div>
                                <h2 className="text-2xl font-black text-[#1E293B]">
                                    {isRTL ? 'إتمام الدفع (تجريبي)' : 'Mock Payment Checkout'}
                                </h2>
                                <p className="text-gray-500 mt-2 text-sm">
                                    {isRTL
                                        ? `للانتقال إلى خطة ${selectedPlanForPayment}، يرجى ملء بيانات الدفع الوهمية.`
                                        : `Switching to the ${selectedPlanForPayment} plan. Fill in mock payment details.`}
                                </p>
                            </div>

                            <form onSubmit={processMockPayment} className="space-y-4">
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">
                                        {isRTL ? 'رقم البطاقة' : 'Card Number'}
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        placeholder="•••• •••• •••• ••••"
                                        defaultValue="4242 4242 4242 4242"
                                        className="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 font-mono text-center outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED]"
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">
                                            {isRTL ? 'تاريخ الانتهاء' : 'Expiry'}
                                        </label>
                                        <input type="text" required placeholder="MM/YY" defaultValue="12/26"
                                            className="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 font-mono text-center outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED]" />
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">CVV</label>
                                        <input type="text" required placeholder="123" defaultValue="123"
                                            className="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 font-mono text-center outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED]" />
                                    </div>
                                </div>

                                <div className="flex gap-3 pt-6">
                                    <button
                                        type="button"
                                        disabled={loading}
                                        onClick={() => setShowPaymentModal(false)}
                                        className="flex-1 px-6 py-4 bg-gray-100 rounded-full text-sm font-bold text-gray-600 hover:bg-gray-200 transition"
                                    >
                                        {isRTL ? 'إلغاء' : 'Cancel'}
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={loading}
                                        className="flex-1 flex items-center justify-center gap-2 px-6 py-4 bg-gray-900 rounded-full text-sm font-bold text-white hover:bg-black transition"
                                    >
                                        {loading ? <Loader2 size={18} className="animate-spin" /> : <Check size={18} />}
                                        {isRTL ? 'دفع وهمي (Mock Pay)' : 'Mock Pay & Upgrade'}
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
