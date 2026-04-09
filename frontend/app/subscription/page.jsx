'use client';

import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthenticationContext';
import { useSubscription } from '@/context/SubscriptionContext';
import { useRouter } from 'next/navigation';

// Components
import SubscriptionPlans from '@/components/subscription/SubscriptionPlans';
import UsageChart from '@/components/subscription/UsageChart';
import CurrentPlan from '@/components/subscription/CurrentPlan';

export default function SubscriptionPage() {
    const { user, loading } = useAuth();
    const { plans, usage, loading: subscriptionLoading, upgradePlan } = useSubscription();
    const router = useRouter();

    const displayPlans = (plans || [])
        .filter(plan => ['free', 'premium'].includes(plan.name))
        .map(plan => ({
            ...plan,
            display_name: plan.name === 'free' ? 'الفئة المجانية' : 'فئة المحترفين (Pro)'
        }));

    useEffect(() => {
        if (!loading && !user) {
            router.push('/login');
            return;
        }
    }, [user, loading, router]);

    const handleUpgrade = async (planName) => {
        const result = await upgradePlan(planName);

        if (result.success) {
            alert('Plan upgraded successfully!');
        } else {
            alert(result.error || 'Upgrade failed. Please try again.');
        }
    };

    if (loading || subscriptionLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin" />
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50 py-12">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header built around the 'Pricing.jsx' aesthetic */}
                <div className="text-center mb-16 space-y-4 relative z-10">
                    <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-blue-500/5 rounded-full blur-[100px] -z-10" />
                    <h1 className="text-3xl md:text-5xl font-bold text-gray-900 tracking-tight pt-8 mb-4">
                        Transparent Scaling <span className="text-blue-500 underline decoration-blue-500/10 underline-offset-4 decoration-8">Strategy</span>.
                    </h1>
                    <p className="text-gray-500 font-medium max-w-xl mx-auto leading-relaxed">
                        No complicated contracts. Choose a plan that suits your current marketplace volume and upgrade as you grow.
                    </p>
                </div>

                {/* Current Plan & Usage */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
                    <div className="lg:col-span-1">
                        <CurrentPlan usage={usage} />
                    </div>
                    <div className="lg:col-span-2">
                        <UsageChart usage={usage} />
                    </div>
                </div>

                {/* Available Plans Component inheriting Pricing UI */}
                <div className="mt-16">
                    <SubscriptionPlans
                        plans={displayPlans}
                        currentPlan={usage?.current_plan}
                        onUpgrade={handleUpgrade}
                    />
                </div>
            </div>
        </div>
    );
}
