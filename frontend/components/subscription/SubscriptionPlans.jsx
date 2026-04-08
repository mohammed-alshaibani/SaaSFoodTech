'use client';

import { useState } from 'react';

export default function SubscriptionPlans({ plans, currentPlan, onUpgrade }) {
    const [loading, setLoading] = useState(null);

    const handleUpgradeClick = async (planName) => {
        setLoading(planName);
        try {
            await onUpgrade(planName);
        } finally {
            setLoading(null);
        }
    };

    const getPlanColor = (planName) => {
        switch (planName) {
            case 'free':
                return 'border-gray-300 bg-gray-50';
            case 'basic':
                return 'border-blue-300 bg-blue-50';
            case 'premium':
                return 'border-purple-300 bg-purple-50';
            case 'enterprise':
                return 'border-yellow-300 bg-yellow-50';
            default:
                return 'border-gray-300 bg-gray-50';
        }
    };

    const getPlanButtonColor = (planName) => {
        switch (planName) {
            case 'free':
                return 'bg-gray-600 hover:bg-gray-700';
            case 'basic':
                return 'bg-blue-600 hover:bg-blue-700';
            case 'premium':
                return 'bg-purple-600 hover:bg-purple-700';
            case 'enterprise':
                return 'bg-yellow-600 hover:bg-yellow-700';
            default:
                return 'bg-gray-600 hover:bg-gray-700';
        }
    };

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            {plans.map((plan) => {
                const isCurrentPlan = plan.name === currentPlan;
                const canUpgrade = !isCurrentPlan && plan.name !== 'free';
                
                return (
                    <div
                        key={plan.id}
                        className={`relative rounded-lg border-2 p-6 ${getPlanColor(plan.name)} ${
                            isCurrentPlan ? 'ring-2 ring-offset-2 ring-blue-500' : ''
                        }`}
                    >
                        {isCurrentPlan && (
                            <div className="absolute top-0 right-0 -translate-y-1/2 translate-x-1/2">
                                <span className="bg-blue-500 text-white text-xs font-bold px-3 py-1 rounded-full">
                                    CURRENT
                                </span>
                            </div>
                        )}

                        <div className="text-center mb-6">
                            <h3 className="text-2xl font-bold text-gray-900 mb-2">
                                {plan.display_name}
                            </h3>
                            <div className="text-4xl font-bold text-gray-900 mb-2">
                                {plan.formatted_price}
                                <span className="text-lg font-normal text-gray-500">
                                    /{plan.billing_cycle}
                                </span>
                            </div>
                            <p className="text-sm text-gray-600">
                                {plan.description}
                            </p>
                        </div>

                        <div className="space-y-3 mb-8">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-gray-600">Monthly Requests</span>
                                <span className="text-sm font-semibold text-gray-900">
                                    {plan.limits.requests_per_month}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-gray-600">Attachments per Request</span>
                                <span className="text-sm font-semibold text-gray-900">
                                    {plan.limits.attachments_per_request}
                                </span>
                            </div>
                            {plan.features.ai_enhancement && (
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">AI Enhancement</span>
                                    <span className="text-sm font-semibold text-green-600">Yes</span>
                                </div>
                            )}
                            {plan.features.priority_support && (
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">Priority Support</span>
                                    <span className="text-sm font-semibold text-green-600">Yes</span>
                                </div>
                            )}
                            {plan.features.api_access && (
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">API Access</span>
                                    <span className="text-sm font-semibold text-green-600">Yes</span>
                                </div>
                            )}
                        </div>

                        <button
                            onClick={() => handleUpgradeClick(plan.name)}
                            disabled={!canUpgrade || loading !== null}
                            className={`w-full py-3 px-4 rounded-md text-white font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${
                                isCurrentPlan
                                    ? 'bg-gray-400 cursor-not-allowed'
                                    : canUpgrade
                                    ? getPlanButtonColor(plan.name)
                                    : 'bg-gray-400 cursor-not-allowed'
                            }`}
                        >
                            {loading === plan.name ? (
                                <div className="flex items-center justify-center">
                                    <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2" />
                                    Processing...
                                </div>
                            ) : isCurrentPlan ? (
                                'Current Plan'
                            ) : plan.name === 'free' ? (
                                'Downgrade'
                            ) : (
                                'Upgrade'
                            )}
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
