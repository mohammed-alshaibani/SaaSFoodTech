<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Exceptions\CannotAcceptOwnOrderException;
use App\Exceptions\OrderAlreadyAcceptedException;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Admins bypass all policy checks automatically.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null; // fall through to the specific method
    }

    /**
     * Determine if the user can list service requests.
     * Scoping (who sees what) is handled inside the controller query.
     */
    public function viewAny(User $user): bool
    {
        return true; // every authenticated user may hit the index; query scope restricts data
    }

    /**
     * Determine if the user can view a single service request.
     */
    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        // Provider Admin / Employee with view_all permission can see everything
        if ($user->can('request.view_all')) {
            return true;
        }

        // Customer can only see their own requests
        if ($user->hasRole('customer') && $serviceRequest->customer_id === $user->id) {
            return true;
        }

        // Providers can see ANY 'pending' request to evaluate it before accepting
        if ($user->hasRole(['provider_admin', 'provider_employee']) && $serviceRequest->status === 'pending') {
            return true;
        }

        // Assigned provider can see their accepted/completed requests
        return $serviceRequest->provider_id === $user->id;
    }

    /**
     * Determine if the user can create a service request.
     */
    public function create(User $user): bool
    {
        return $user->can('request.create');
    }

    /**
     * Determine if a provider can accept a pending service request.
     */
    public function accept(User $user, ServiceRequest $serviceRequest): bool
    {
        if (!$user->can('request.accept')) {
            return false;
        }

        // Prevents a user from accepting THEIR OWN request even if they have provider role
        if ($serviceRequest->customer_id === $user->id) {
            throw new CannotAcceptOwnOrderException();
        }

        // Check if request is already accepted by another provider
        if ($serviceRequest->provider_id !== null && $serviceRequest->provider_id !== $user->id) {
            throw new OrderAlreadyAcceptedException();
        }

        // Request must still be pending
        return $serviceRequest->status === 'pending';
    }

    /**
     * Determine if a provider can mark a request as completed.
     */
    public function complete(User $user, ServiceRequest $serviceRequest): bool
    {
        if (!$user->can('request.complete')) {
            return false;
        }

        // Only the assigned provider can complete it, and only when it is accepted
        return $serviceRequest->provider_id === $user->id
            && $serviceRequest->status === 'accepted';
    }

    /**
     * Determine if the user can view nearby service requests.
     */
    public function viewNearby(User $user): bool
    {
        // Only providers can view nearby requests
        return $user->hasRole(['provider_admin', 'provider_employee']) && 
               $user->can('request.view_nearby');
    }
}
