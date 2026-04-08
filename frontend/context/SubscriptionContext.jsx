'use client';

import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { useAuth } from './AuthenticationContext';
import api from '@/lib/api';

// Types
/**
 * @typedef {Object} SubscriptionPlan
 * @property {number} id
 * @property {string} name
 * @property {string} display_name
 * @property {number} price
 * @property {string} billing_cycle
 * @property {Object} features
 * @property {Object} limits
 */

/**
 * @typedef {Object} Usage
 * @property {string} current_plan
 * @property {number} requests_used
 * @property {number|string} requests_limit
 * @property {number|string} requests_remaining
 * @property {number} percentage_used
 * @property {boolean} limit_reached
 * @property {string} reset_date
 * @property {Object|null} subscription
 */

const SubscriptionContext = createContext(undefined);

// Subscription Service Class
class SubscriptionService {
    constructor() {
        this.baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
    }

    async getPlans() {
        const response = await api.get('/subscription/plans');
        return response.data.data;
    }

    async getUsage() {
        const response = await api.get('/subscription/usage');
        return response.data.data;
    }

    async upgradePlan(planName, paymentMethod = 'credit_card') {
        const response = await api.post('/subscription/upgrade', {
            plan: planName,
            payment_method: paymentMethod,
        });
        return response.data;
    }

    async cancelSubscription(reason) {
        const response = await api.post('/subscription/cancel', { reason });
        return response.data;
    }

    canCreateRequest(usage) {
        if (!usage) return false;
        return !usage.limit_reached;
    }

    hasFeatureAccess(usage, feature) {
        if (!usage || !usage.subscription) return false;
        
        const planFeatures = usage.subscription.plan?.features || {};
        return planFeatures[feature] === true;
    }

    getPlanLimits(usage) {
        if (!usage || !usage.subscription) return {};
        
        return usage.subscription.plan?.limits || {};
    }

    getDaysUntilReset(usage) {
        if (!usage || !usage.reset_date) return 0;
        
        const resetDate = new Date(usage.reset_date);
        const today = new Date();
        const diffTime = resetDate - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        return Math.max(0, diffDays);
    }

    getUsageColor(percentage) {
        if (percentage >= 90) return 'text-red-600';
        if (percentage >= 75) return 'text-yellow-600';
        return 'text-green-600';
    }

    getUsageBgColor(percentage) {
        if (percentage >= 90) return 'bg-red-100';
        if (percentage >= 75) return 'bg-yellow-100';
        return 'bg-green-100';
    }

    getProgressBarColor(percentage) {
        if (percentage >= 90) return 'bg-red-500';
        if (percentage >= 75) return 'bg-yellow-500';
        return 'bg-green-500';
    }
}

// Provider Component
export function SubscriptionProvider({ children }) {
    const { user, isAuthenticated } = useAuth();
    const [plans, setPlans] = useState([]);
    const [usage, setUsage] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const subscriptionService = new SubscriptionService();

    // Fetch subscription plans
    const fetchPlans = useCallback(async () => {
        if (!isAuthenticated) return;

        try {
            const plansData = await subscriptionService.getPlans();
            setPlans(plansData);
            setError(null);
        } catch (error) {
            setError(error.message);
            console.error('Failed to fetch plans:', error);
        }
    }, [isAuthenticated]);

    // Fetch usage data
    const fetchUsage = useCallback(async () => {
        if (!isAuthenticated) return;

        try {
            const usageData = await subscriptionService.getUsage();
            setUsage(usageData);
            setError(null);
        } catch (error) {
            setError(error.message);
            console.error('Failed to fetch usage:', error);
        }
    }, [isAuthenticated]);

    // Initialize data when user is authenticated
    useEffect(() => {
        if (isAuthenticated) {
            fetchPlans();
            fetchUsage();
        } else {
            setPlans([]);
            setUsage(null);
        }
    }, [isAuthenticated, fetchPlans, fetchUsage]);

    // Upgrade subscription plan
    const upgradePlan = useCallback(async (planName, paymentMethod) => {
        setLoading(true);
        setError(null);

        try {
            const result = await subscriptionService.upgradePlan(planName, paymentMethod);
            
            // Refresh usage data after successful upgrade
            await fetchUsage();
            
            return { success: true, data: result };
        } catch (error) {
            setError(error.message);
            return { success: false, error: error.message };
        } finally {
            setLoading(false);
        }
    }, [fetchUsage]);

    // Cancel subscription
    const cancelSubscription = useCallback(async (reason) => {
        setLoading(true);
        setError(null);

        try {
            const result = await subscriptionService.cancelSubscription(reason);
            
            // Refresh usage data after cancellation
            await fetchUsage();
            
            return { success: true, data: result };
        } catch (error) {
            setError(error.message);
            return { success: false, error: error.message };
        } finally {
            setLoading(false);
        }
    }, [fetchUsage]);

    // Utility methods
    const canCreateRequest = useCallback(() => {
        return subscriptionService.canCreateRequest(usage);
    }, [usage]);

    const hasFeatureAccess = useCallback((feature) => {
        return subscriptionService.hasFeatureAccess(usage, feature);
    }, [usage]);

    const getPlanLimits = useCallback(() => {
        return subscriptionService.getPlanLimits(usage);
    }, [usage]);

    const getDaysUntilReset = useCallback(() => {
        return subscriptionService.getDaysUntilReset(usage);
    }, [usage]);

    const getUsageColor = useCallback((percentage) => {
        return subscriptionService.getUsageColor(percentage);
    }, []);

    const getUsageBgColor = useCallback((percentage) => {
        return subscriptionService.getUsageBgColor(percentage);
    }, []);

    const getProgressBarColor = useCallback((percentage) => {
        return subscriptionService.getProgressBarColor(percentage);
    }, []);

    const getCurrentPlan = useCallback(() => {
        return usage?.current_plan || 'free';
    }, [usage]);

    const getUsagePercentage = useCallback(() => {
        return usage?.percentage_used || 0;
    }, [usage]);

    const clearError = useCallback(() => {
        setError(null);
    }, []);

    const refreshData = useCallback(() => {
        fetchPlans();
        fetchUsage();
    }, [fetchPlans, fetchUsage]);

    const value = {
        // State
        plans,
        usage,
        loading,
        error,

        // Actions
        fetchPlans,
        fetchUsage,
        upgradePlan,
        cancelSubscription,
        refreshData,

        // Utilities
        canCreateRequest,
        hasFeatureAccess,
        getPlanLimits,
        getDaysUntilReset,
        getCurrentPlan,
        getUsagePercentage,
        getUsageColor,
        getUsageBgColor,
        getProgressBarColor,
        clearError,
    };

    return (
        <SubscriptionContext.Provider value={value}>
            {children}
        </SubscriptionContext.Provider>
    );
}

// Hook
export function useSubscription() {
    const context = useContext(SubscriptionContext);
    if (context === undefined) {
        throw new Error('useSubscription must be used within a SubscriptionProvider');
    }
    return context;
}

// Export service for testing
export { SubscriptionService };
