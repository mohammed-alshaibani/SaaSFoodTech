<?php

namespace App\Services\Subscription;

use App\Models\User;

interface PlanResolverInterface
{
    /**
     * Resolve the current plan for a user.
     */
    public function resolve(User $user): string;

    /**
     * Check if the resolver can handle the given user.
     */
    public function canHandle(User $user): bool;

    /**
     * Get the priority of this resolver (higher = more priority).
     */
    public function getPriority(): int;

    /**
     * Get resolver name.
     */
    public function getName(): string;
}
