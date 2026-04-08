'use client';

import { AuthenticationProvider } from './AuthenticationContext';
import { AuthorizationProvider } from './AuthorizationContext';
import { SubscriptionProvider } from './SubscriptionContext';

export function AppProvider({ children }) {
    return (
        <AuthenticationProvider>
            <AuthorizationProvider>
                <SubscriptionProvider>
                    {children}
                </SubscriptionProvider>
            </AuthorizationProvider>
        </AuthenticationProvider>
    );
}

// Combined hook for convenience
export function useApp() {
    const auth = useAuth();
    const authorization = useAuthorization();
    const subscription = useSubscription();

    return {
        auth,
        authorization,
        subscription,
        // Convenience methods
        isAuthenticated: auth.isAuthenticated,
        user: auth.user,
        canCreateRequest: subscription.canCreateRequest,
        dashboardPath: authorization.getDashboardPath(),
        currentPlan: subscription.getCurrentPlan(),
    };
}

// Import the individual hooks for the combined hook
import { useAuth } from './AuthenticationContext';
import { useAuthorization } from './AuthorizationContext';
import { useSubscription } from './SubscriptionContext';
