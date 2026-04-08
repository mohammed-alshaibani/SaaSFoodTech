<?php

namespace App\Services\Subscription;

use App\Models\User;

class LegacyPlanResolver implements PlanResolverInterface
{
    public function resolve(User $user): string
    {
        return $user->plan ?? 'free';
    }

    public function canHandle(User $user): bool
    {
        // Always can handle as fallback
        return true;
    }

    public function getPriority(): int
    {
        return 10; // Low priority - fallback resolver
    }

    public function getName(): string
    {
        return 'legacy';
    }
}
