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
                {/* Header */}
                <div className="text-center mb-12">
                    <h1 className="text-4xl font-bold text-gray-900 mb-4">
                        Manage Your Subscription
                    </h1>
                    <p className="text-xl text-gray-600">
                        Upgrade your plan to unlock more features and higher limits
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

                {/* Available Plans */}
                <div className="mb-12">
                    <h2 className="text-2xl font-bold text-gray-900 mb-8 text-center">
                        Available Plans
                    </h2>
                    <SubscriptionPlans 
                        plans={plans} 
                        currentPlan={usage?.current_plan}
                        onUpgrade={handleUpgrade}
                    />
                </div>

                {/* Feature Comparison */}
                <div className="bg-white rounded-lg shadow-sm border p-8">
                    <h2 className="text-2xl font-bold text-gray-900 mb-6 text-center">
                        Feature Comparison
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Feature
                                    </th>
                                    {plans.map((plan) => (
                                        <th key={plan.name} className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {plan.display_name}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        Monthly Requests
                                    </td>
                                    {plans.map((plan) => (
                                        <td key={plan.name} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            {plan.limits.requests_per_month}
                                        </td>
                                    ))}
                                </tr>
                                <tr>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        Attachments per Request
                                    </td>
                                    {plans.map((plan) => (
                                        <td key={plan.name} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            {plan.limits.attachments_per_request}
                                        </td>
                                    ))}
                                </tr>
                                <tr>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        AI Enhancement
                                    </td>
                                    {plans.map((plan) => (
                                        <td key={plan.name} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            {plan.features.ai_enhancement ? 'Yes' : 'No'}
                                        </td>
                                    ))}
                                </tr>
                                <tr>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        Priority Support
                                    </td>
                                    {plans.map((plan) => (
                                        <td key={plan.name} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            {plan.features.priority_support ? 'Yes' : 'No'}
                                        </td>
                                    ))}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
}
