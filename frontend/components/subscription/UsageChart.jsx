'use client';

export default function UsageChart({ usage }) {
    if (!usage) {
        return (
            <div className="bg-white rounded-lg shadow-sm border p-6">
                <div className="animate-pulse">
                    <div className="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
                    <div className="space-y-3">
                        <div className="h-3 bg-gray-200 rounded w-full"></div>
                        <div className="h-3 bg-gray-200 rounded w-3/4"></div>
                        <div className="h-3 bg-gray-200 rounded w-1/2"></div>
                    </div>
                </div>
            </div>
        );
    }

    const getUsageColor = (percentage) => {
        if (percentage >= 90) return 'text-red-600';
        if (percentage >= 75) return 'text-yellow-600';
        return 'text-green-600';
    };

    const getUsageBgColor = (percentage) => {
        if (percentage >= 90) return 'bg-red-100';
        if (percentage >= 75) return 'bg-yellow-100';
        return 'bg-green-100';
    };

    const getProgressBarColor = (percentage) => {
        if (percentage >= 90) return 'bg-red-500';
        if (percentage >= 75) return 'bg-yellow-500';
        return 'bg-green-500';
    };

    // Mock data for the last 7 days
    const mockDailyUsage = [
        { day: 'Mon', requests: 2 },
        { day: 'Tue', requests: 1 },
        { day: 'Wed', requests: 3 },
        { day: 'Thu', requests: 0 },
        { day: 'Fri', requests: 1 },
        { day: 'Sat', requests: 2 },
        { day: 'Sun', requests: usage.requests_used % 7 || 1 },
    ];

    const maxDailyRequests = Math.max(...mockDailyUsage.map(d => d.requests), 1);

    return (
        <div className="bg-white rounded-lg shadow-sm border p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-6">Usage Overview</h2>

            {/* Main Usage Stats */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div className="text-center">
                    <div className={`text-3xl font-bold ${getUsageColor(usage.percentage_used)}`}>
                        {usage.requests_used}
                    </div>
                    <p className="text-sm text-gray-600 mt-1">Requests This Month</p>
                </div>
                <div className="text-center">
                    <div className="text-3xl font-bold text-gray-900">
                        {usage.requests_remaining}
                    </div>
                    <p className="text-sm text-gray-600 mt-1">Remaining</p>
                </div>
                <div className="text-center">
                    <div className={`text-3xl font-bold ${getUsageColor(usage.percentage_used)}`}>
                        {usage.percentage_used}%
                    </div>
                    <p className="text-sm text-gray-600 mt-1">Used</p>
                </div>
            </div>

            {/* Progress Bar */}
            <div className="mb-8">
                <div className="flex justify-between items-center mb-2">
                    <span className="text-sm font-medium text-gray-700">Monthly Usage</span>
                    <span className={`text-sm font-medium ${getUsageColor(usage.percentage_used)}`}>
                        {usage.requests_used} / {usage.requests_limit}
                    </span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-3">
                    <div
                        className={`h-3 rounded-full transition-all duration-500 ${getProgressBarColor(usage.percentage_used)}`}
                        style={{ width: `${Math.min(usage.percentage_used, 100)}%` }}
                    />
                </div>
                <p className="text-xs text-gray-500 mt-1">
                    {usage.limit_reached 
                        ? 'You\'ve reached your monthly limit. Upgrade to continue.'
                        : usage.percentage_used > 75 
                        ? 'You\'re approaching your monthly limit.'
                        : 'You have plenty of requests remaining.'
                    }
                </p>
            </div>

            {/* Daily Usage Chart */}
            <div>
                <h3 className="text-sm font-medium text-gray-700 mb-4">Last 7 Days</h3>
                <div className="space-y-3">
                    {mockDailyUsage.map((day, index) => (
                        <div key={day.day} className="flex items-center space-x-3">
                            <div className="w-8 text-xs text-gray-600 font-medium">
                                {day.day}
                            </div>
                            <div className="flex-1 bg-gray-200 rounded-full h-2">
                                <div
                                    className="bg-blue-500 h-2 rounded-full transition-all duration-300"
                                    style={{ width: `${(day.requests / maxDailyRequests) * 100}%` }}
                                />
                            </div>
                            <div className="w-8 text-xs text-gray-600 text-right">
                                {day.requests}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Usage Tips */}
            <div className={`mt-8 p-4 rounded-lg ${getUsageBgColor(usage.percentage_used)}`}>
                <div className="flex items-start space-x-3">
                    <div className="flex-shrink-0">
                        <svg className={`w-5 h-5 ${getUsageColor(usage.percentage_used)}`} fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                        </svg>
                    </div>
                    <div className="flex-1">
                        <h4 className={`text-sm font-medium ${getUsageColor(usage.percentage_used)}`}>
                            {usage.limit_reached 
                                ? 'Limit Reached'
                                : usage.percentage_used > 75 
                                ? 'Getting Close to Limit'
                                : 'Good Usage'
                            }
                        </h4>
                        <p className={`text-sm mt-1 ${getUsageColor(usage.percentage_used)}`}>
                            {usage.limit_reached 
                                ? 'You\'ve used all your requests for this month. Consider upgrading to a higher plan for unlimited access.'
                                : usage.percentage_used > 75 
                                ? 'You\'ve used most of your requests this month. Upgrade now to avoid service interruption.'
                                : 'You\'re using your plan efficiently. Keep track of your usage to make the most of your subscription.'
                            }
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
