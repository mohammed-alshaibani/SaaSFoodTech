<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\ServiceRequestSimple;
use App\Models\User;
use App\Policies\ServiceRequestPolicy;

class AuthServiceProviderSimple extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     */
    protected $policies = [
        ServiceRequestSimple::class => ServiceRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Implicitly grant "Super Admin" users all permissions
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }
        });
    }
}
