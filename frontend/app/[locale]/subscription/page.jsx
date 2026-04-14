'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import api from '@/lib/api';
import Navbar from '@/components/landing/Navbar';
import Footer from '@/components/landing/Footer';
import {
    Check,
    Crown,
    Zap,
    Shield,
    ArrowRight,
    Loader2,
    AlertCircle,
    CheckCircle2,
    Sparkles,
    Building2,
    Users,
    Headphones,
    Globe,
    Lock
} from 'lucide-react';

export default function SubscriptionPage() {
    const { user, isAuthenticated, refreshUser } = useAuth();
    const { t, language } = useI18n();
    const router = useRouter();
    const isRTL = language === 'ar';

    const [plans, setPlans] = useState([]);
    const [currentPlan, setCurrentPlan] = useState('free');
    const [loading, setLoading] = useState(true);
    const [upgrading, setUpgrading] = useState(null);
    const [message, setMessage] = useState(null);

    const fetchPlans = useCallback(async () => {
        try {
            const res = await api.get('/subscription/plans');
            setPlans(res.data.data || []);
        } catch (err) {
            console.error('Failed to fetch plans:', err);
            // Fallback plans if API fails
            setPlans([
                {
                    id: 1,
                    name: 'free',
                    display_name: 'Free',
                    description: 'Perfect for getting started',
                    price: 0,
                    features: ['3 requests per month', 'Basic support', 'Standard delivery'],
                    color: 'gray'
                },
                {
                    id: 2,
                    name: 'basic',
                    display_name: 'Basic',
                    description: 'Great for small teams',
                    price: 29.99,
                    features: ['50 requests per month', 'Priority support', 'Fast delivery', 'Basic analytics'],
                    color: 'blue',
                    popular: true
                },
                {
                    id: 3,
                    name: 'premium',
                    display_name: 'Premium',
                    description: 'For growing businesses',
                    price: 79.99,
                    features: ['200 requests per month', '24/7 Priority support', 'Express delivery', 'Advanced analytics', 'AI enhancement'],
                    color: 'purple'
                },
                {
                    id: 4,
                    name: 'enterprise',
                    display_name: 'Enterprise',
                    description: 'Complete solution',
                    price: 199.99,
                    features: ['Unlimited requests', 'Dedicated support', 'Custom integrations', 'API access', 'White-label options'],
                    color: 'emerald'
                }
            ]);
        }
    }, []);

    useEffect(() => {
        if (user?.plan) {
            setCurrentPlan(user.plan);
        }
    }, [user?.plan]);

    useEffect(() => {
        fetchPlans().finally(() => setLoading(false));
    }, [fetchPlans]);

    const handleUpgrade = async (planName) => {
        if (!isAuthenticated) {
            router.push('/register?redirect=/dashboard/customer/plans');
            return;
        }

        router.push('/dashboard/customer/plans');
    };

    const getPlanIcon = (planName) => {
        const icons = {
            free: Globe,
            basic: Zap,
            premium: Crown,
            enterprise: Building2,
        };
        return icons[planName] || Sparkles;
    };

    const getPlanColors = (planName, isCurrent) => {
        const colors = {
            free: isCurrent ? 'border-gray-400 bg-gray-50' : 'border-gray-200 hover:border-gray-300 bg-white',
            basic: isCurrent ? 'border-blue-500 bg-blue-50' : 'border-blue-200 hover:border-blue-400 bg-white',
            premium: isCurrent ? 'border-purple-500 bg-purple-50' : 'border-purple-200 hover:border-purple-400 bg-white shadow-purple-100',
            enterprise: isCurrent ? 'border-emerald-500 bg-emerald-50' : 'border-emerald-200 hover:border-emerald-400 bg-white',
        };
        return colors[planName] || colors.free;
    };

    if (loading) {
        return (
            <div className="min-h-screen bg-gray-50">
                <Navbar />
                <div className="flex items-center justify-center h-96">
                    <Loader2 className="animate-spin h-12 w-12 text-blue-600" />
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50">
            <Navbar />

            {/* Hero Section */}
            <div className="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 text-white py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className={`text-center ${isRTL ? 'rtl' : ''}`}>
                        <div className="inline-flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full text-sm font-semibold mb-6 border border-white/20">
                            <Crown size={16} />
                            {t('subscription.badge') || 'Choose Your Plan'}
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black mb-6">
                            {t('subscription.title') || 'Simple, Transparent Pricing'}
                        </h1>
                        <p className="text-xl text-blue-100 max-w-2xl mx-auto">
                            {t('subscription.subtitle') || 'Choose the perfect plan for your business needs. Upgrade or downgrade anytime.'}
                        </p>
                    </div>
                </div>
            </div>

            {/* Plans Section */}
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                {/* Message */}
                {message && (
                    <div className={`max-w-2xl mx-auto mb-8 p-4 rounded-xl border ${message.type === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'}`}>
                        <div className="flex items-center justify-center gap-2">
                            {message.type === 'error' ? <AlertCircle size={20} /> : <CheckCircle2 size={20} />}
                            <span className="font-bold">{message.text}</span>
                        </div>
                    </div>
                )}

                {/* Current Plan Banner */}
                {isAuthenticated && currentPlan && (
                    <div className="max-w-2xl mx-auto mb-12 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center text-white">
                                    <Crown size={24} />
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600">{t('subscription.currentPlan') || 'Current Plan'}</p>
                                    <p className="text-xl font-bold text-gray-900 capitalize">{currentPlan}</p>
                                </div>
                            </div>
                            <span className="px-4 py-2 bg-blue-100 text-blue-700 rounded-full text-sm font-bold">
                                {t('subscription.active') || 'Active'}
                            </span>
                        </div>
                    </div>
                )}

                {/* Plans Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {plans.map((plan) => {
                        const Icon = getPlanIcon(plan.name);
                        const isCurrent = currentPlan === plan.name;
                        const isUpgrading = upgrading === plan.name;

                        return (
                            <div
                                key={plan.id}
                                className={`relative rounded-2xl border-2 p-6 transition-all duration-300 ${getPlanColors(plan.name, isCurrent)} ${plan.popular && !isCurrent ? 'shadow-xl shadow-purple-100 scale-105' : 'shadow-lg'}`}
                            >
                                {/* Popular Badge */}
                                {plan.popular && !isCurrent && (
                                    <div className="absolute -top-4 left-1/2 -translate-x-1/2">
                                        <span className="px-4 py-1 bg-gradient-to-r from-purple-500 to-pink-500 text-white text-sm font-bold rounded-full shadow-lg">
                                            {t('subscription.mostPopular') || 'Most Popular'}
                                        </span>
                                    </div>
                                )}

                                {/* Current Badge */}
                                {isCurrent && (
                                    <div className="absolute -top-4 left-1/2 -translate-x-1/2">
                                        <span className="px-4 py-1 bg-emerald-500 text-white text-sm font-bold rounded-full shadow-lg">
                                            {t('subscription.yourPlan') || 'Your Plan'}
                                        </span>
                                    </div>
                                )}

                                {/* Plan Header */}
                                <div className="text-center mb-6">
                                    <div className={`w-16 h-16 mx-auto rounded-2xl flex items-center justify-center mb-4 ${plan.name === 'free' ? 'bg-gray-100 text-gray-600' :
                                            plan.name === 'basic' ? 'bg-blue-100 text-blue-600' :
                                                plan.name === 'premium' ? 'bg-purple-100 text-purple-600' :
                                                    'bg-emerald-100 text-emerald-600'
                                        }`}>
                                        <Icon size={32} />
                                    </div>
                                    <h3 className="text-xl font-black text-gray-900 mb-1">
                                        {plan.display_name || plan.name}
                                    </h3>
                                    <p className="text-sm text-gray-500">{plan.description}</p>
                                </div>

                                {/* Price */}
                                <div className="text-center mb-6">
                                    <div className="flex items-baseline justify-center gap-1">
                                        <span className="text-4xl font-black text-gray-900">
                                            ${plan.price || 0}
                                        </span>
                                        <span className="text-gray-500">/{t('subscription.month') || 'month'}</span>
                                    </div>
                                </div>

                                {/* Features */}
                                <ul className="space-y-3 mb-8">
                                    {(plan.features || []).map((feature, idx) => (
                                        <li key={idx} className="flex items-start gap-3">
                                            <Check size={18} className={`mt-0.5 flex-shrink-0 ${plan.name === 'free' ? 'text-gray-500' :
                                                    plan.name === 'basic' ? 'text-blue-500' :
                                                        plan.name === 'premium' ? 'text-purple-500' :
                                                            'text-emerald-500'
                                                }`} />
                                            <span className="text-sm text-gray-600">{feature}</span>
                                        </li>
                                    ))}
                                </ul>

                                {/* CTA Button */}
                                <button
                                    onClick={() => handleUpgrade(plan.name)}
                                    disabled={isCurrent || isUpgrading}
                                    className={`w-full py-3 px-4 rounded-xl font-bold transition-all duration-200 ${isCurrent
                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                            : isUpgrading
                                                ? 'bg-gray-100 text-gray-400 cursor-wait'
                                                : plan.name === 'free'
                                                    ? 'bg-gray-900 text-white hover:bg-gray-800'
                                                    : plan.name === 'basic'
                                                        ? 'bg-blue-600 text-white hover:bg-blue-700 shadow-lg shadow-blue-200'
                                                        : plan.name === 'premium'
                                                            ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white hover:from-purple-700 hover:to-pink-700 shadow-lg shadow-purple-200'
                                                            : 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-lg shadow-emerald-200'
                                        }`}
                                >
                                    {isUpgrading ? (
                                        <span className="flex items-center justify-center gap-2">
                                            <Loader2 size={18} className="animate-spin" />
                                            {t('subscription.processing') || 'Processing...'}
                                        </span>
                                    ) : isCurrent ? (
                                        t('subscription.current') || 'Current Plan'
                                    ) : (
                                        <span className="flex items-center justify-center gap-2">
                                            {plan.price === 0 ? (t('subscription.getStarted') || 'Get Started') : (t('subscription.upgrade') || 'Upgrade')}
                                            <ArrowRight size={18} />
                                        </span>
                                    )}
                                </button>
                            </div>
                        );
                    })}
                </div>

                {/* Trust Badges */}
                <div className="mt-16 grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
                    <div className="flex items-center justify-center gap-3 text-gray-600">
                        <div className="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                            <Shield size={24} className="text-gray-600" />
                        </div>
                        <div>
                            <p className="font-bold text-gray-900">{t('subscription.secure') || 'Secure Payments'}</p>
                            <p className="text-sm">{t('subscription.encrypted') || '256-bit SSL encrypted'}</p>
                        </div>
                    </div>
                    <div className="flex items-center justify-center gap-3 text-gray-600">
                        <div className="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                            <Headphones size={24} className="text-gray-600" />
                        </div>
                        <div>
                            <p className="font-bold text-gray-900">{t('subscription.support') || '24/7 Support'}</p>
                            <p className="text-sm">{t('subscription.alwaysHere') || 'Always here to help'}</p>
                        </div>
                    </div>
                    <div className="flex items-center justify-center gap-3 text-gray-600">
                        <div className="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                            <Lock size={24} className="text-gray-600" />
                        </div>
                        <div>
                            <p className="font-bold text-gray-900">{t('subscription.cancel') || 'Cancel Anytime'}</p>
                            <p className="text-sm">{t('subscription.noContracts') || 'No long-term contracts'}</p>
                        </div>
                    </div>
                </div>

                {/* FAQ Link */}
                <div className="mt-12 text-center">
                    <p className="text-gray-600">
                        {t('subscription.questions') || 'Have questions?'} {' '}
                        <a href="#" className="text-blue-600 font-bold hover:underline">
                            {t('subscription.contactUs') || 'Contact our team'}
                        </a>
                    </p>
                </div>
            </div>

            <Footer />
        </div>
    );
}
