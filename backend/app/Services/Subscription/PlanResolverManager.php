<?php

namespace App\Services\Subscription;

use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class PlanResolverManager
{
    protected static array $resolvers = [];

    /**
     * Register a plan resolver.
     */
    public static function register(PlanResolverInterface $resolver): void
    {
        self::$resolvers[] = $resolver;
        
        // Sort by priority (highest first)
        usort(self::$resolvers, function ($a, $b) {
            return $b->getPriority() - $a->getPriority();
        });
    }

    /**
     * Resolve the current plan for a user.
     */
    public static function resolve(User $user): string
    {
        foreach (self::$resolvers as $resolver) {
            if ($resolver->canHandle($user)) {
                try {
                    $plan = $resolver->resolve($user);
                    
                    Log::debug('Plan resolved', [
                        'user_id' => $user->id,
                        'resolver' => $resolver->getName(),
                        'plan' => $plan,
                    ]);
                    
                    return $plan;
                } catch (\Exception $e) {
                    Log::error('Plan resolver error', [
                        'resolver' => $resolver->getName(),
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                    
                    // Continue to next resolver
                    continue;
                }
            }
        }

        // Default fallback
        Log::warning('No plan resolver could handle user', [
            'user_id' => $user->id,
            'registered_resolvers' => array_map(fn($r) => $r->getName(), self::$resolvers),
        ]);

        return 'free';
    }

    /**
     * Get all registered resolvers.
     */
    public static function getResolvers(): array
    {
        return self::$resolvers;
    }

    /**
     * Get resolver by name.
     */
    public static function getResolver(string $name): ?PlanResolverInterface
    {
        foreach (self::$resolvers as $resolver) {
            if ($resolver->getName() === $name) {
                return $resolver;
            }
        }

        return null;
    }

    /**
     * Initialize default resolvers.
     */
    public static function initialize(): void
    {
        self::clear();
        
        // Register default resolvers
        self::register(App::make(SubscriptionPlanResolver::class));
        self::register(App::make(LegacyPlanResolver::class));
        
        Log::info('Plan resolvers initialized', [
            'resolvers' => array_map(fn($r) => $r->getName(), self::$resolvers),
        ]);
    }

    /**
     * Clear all registered resolvers.
     */
    public static function clear(): void
    {
        self::$resolvers = [];
    }

    /**
     * Get resolver information.
     */
    public static function getResolverInfo(): array
    {
        $info = [];

        foreach (self::$resolvers as $resolver) {
            $info[] = [
                'name' => $resolver->getName(),
                'priority' => $resolver->getPriority(),
                'class' => get_class($resolver),
            ];
        }

        return $info;
    }

    /**
     * Test all resolvers with a user.
     */
    public static function testResolvers(User $user): array
    {
        $results = [];

        foreach (self::$resolvers as $resolver) {
            $canHandle = $resolver->canHandle($user);
            $plan = null;

            if ($canHandle) {
                try {
                    $plan = $resolver->resolve($user);
                } catch (\Exception $e) {
                    $plan = 'Error: ' . $e->getMessage();
                }
            }

            $results[] = [
                'name' => $resolver->getName(),
                'priority' => $resolver->getPriority(),
                'can_handle' => $canHandle,
                'resolved_plan' => $plan,
            ];
        }

        return $results;
    }
}
