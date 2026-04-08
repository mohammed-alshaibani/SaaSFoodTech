'use client';

export default function CurrentPlan({ usage }) {
    if (!usage) {
        return (
            <div className="bg-white rounded-lg shadow-sm border p-6">
                <div className="animate-pulse">
                    <div className="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
                    <div className="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
                    <div className="h-4 bg-gray-200 rounded w-full"></div>
                </div>
            </div>
        );
    }

    const getPlanColor = (planName) => {
        switch (planName) {
            case 'free':
                return 'bg-gray-100 text-gray-800';
            case 'basic':
                return 'bg-blue-100 text-blue-800';
            case 'premium':
                return 'bg-purple-100 text-purple-800';
            case 'enterprise':
                return 'bg-yellow-100 text-yellow-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const getPlanIcon = (planName) => {
        switch (planName) {
            case 'free':
                return 'sparkles';
            case 'basic':
                return 'zap';
            case 'premium':
                return 'star';
            case 'enterprise':
                return 'crown';
            default:
                return 'package';
        }
    };

    return (
        <div className="bg-white rounded-lg shadow-sm border p-6">
            <div className="flex items-center justify-between mb-6">
                <h2 className="text-lg font-semibold text-gray-900">Current Plan</h2>
                <span className={`px-3 py-1 rounded-full text-xs font-medium ${getPlanColor(usage.current_plan)}`}>
                    {usage.current_plan.toUpperCase()}
                </span>
            </div>

            <div className="space-y-4">
                {/* Plan Status */}
                <div className="flex items-center space-x-3">
                    <div className="flex-shrink-0">
                        <div className={`w-10 h-10 rounded-full flex items-center justify-center ${getPlanColor(usage.current_plan)}`}>
                            <span className="text-lg">
                                {getPlanIcon(usage.current_plan) === 'sparkles' && 'sparkles'}
                                {getPlanIcon(usage.current_plan) === 'zap' && 'zap'}
                                {getPlanIcon(usage.current_plan) === 'star' && 'star'}
                                {getPlanIcon(usage.current_plan) === 'crown' && 'crown'}
                            </span>
                        </div>
                    </div>
                    <div>
                        <p className="text-sm font-medium text-gray-900">
                            {usage.current_plan.charAt(0).toUpperCase() + usage.current_plan.slice(1)} Plan
                        </p>
                        <p className="text-xs text-gray-500">
                            {usage.subscription ? `Active since ${new Date(usage.subscription.starts_at).toLocaleDateString()}` : 'Legacy plan'}
                        </p>
                    </div>
                </div>

                {/* Usage Stats */}
                <div className="border-t pt-4">
                    <div className="flex justify-between items-center mb-2">
                        <span className="text-sm text-gray-600">Monthly Requests</span>
                        <span className="text-sm font-medium text-gray-900">
                            {usage.requests_used} / {usage.requests_limit}
                        </span>
                    </div>
                    
                    {/* Progress Bar */}
                    <div className="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div
                            className={`h-2 rounded-full transition-all duration-300 ${
                                usage.limit_reached 
                                    ? 'bg-red-500' 
                                    : usage.percentage_used > 80 
                                    ? 'bg-yellow-500' 
                                    : 'bg-green-500'
                            }`}
                            style={{ width: `${Math.min(usage.percentage_used, 100)}%` }}
                        />
                    </div>
                    
                    <p className="text-xs text-gray-500">
                        {usage.limit_reached 
                            ? 'Limit reached! Upgrade to continue using the service.'
                            : `${usage.requests_remaining} requests remaining`
                        }
                    </p>
                </div>

                {/* Reset Date */}
                <div className="border-t pt-4">
                    <p className="text-xs text-gray-500">
                        Usage resets on {new Date(usage.reset_date).toLocaleDateString()}
                    </p>
                </div>

                {/* Subscription Details */}
                {usage.subscription && (
                    <div className="border-t pt-4">
                        <h3 className="text-sm font-medium text-gray-900 mb-2">Subscription Details</h3>
                        <div className="space-y-1">
                            <div className="flex justify-between text-xs">
                                <span className="text-gray-600">Status</span>
                                <span className={`font-medium ${
                                    usage.subscription.status === 'active' ? 'text-green-600' : 'text-gray-600'
                                }`}>
                                    {usage.subscription.status.charAt(0).toUpperCase() + usage.subscription.status.slice(1)}
                                </span>
                            </div>
                            {usage.subscription.is_in_trial && (
                                <div className="flex justify-between text-xs">
                                    <span className="text-gray-600">Trial</span>
                                    <span className="font-medium text-blue-600">
                                        {usage.subscription.days_remaining > 0 
                                            ? `${usage.subscription.days_remaining} days left`
                                            : 'Expires today'
                                        }
                                    </span>
                                </div>
                            )}
                            {usage.subscription.ends_at && (
                                <div className="flex justify-between text-xs">
                                    <span className="text-gray-600">Renews</span>
                                    <span className="font-medium text-gray-900">
                                        {new Date(usage.subscription.ends_at).toLocaleDateString()}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Upgrade CTA */}
                {usage.limit_reached && (
                    <div className="border-t pt-4">
                        <button className="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                            Upgrade Your Plan
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
