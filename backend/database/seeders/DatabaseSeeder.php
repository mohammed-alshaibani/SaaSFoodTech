<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ServiceRequest;
use App\Models\SubscriptionPlan;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SubscriptionPlanSeeder::class,
        ]);

        // Get the plan models for later use
        $freePlan = SubscriptionPlan::where('name', 'free')->first();
        $premiumPlan = SubscriptionPlan::where('name', 'premium')->first();

        // Create test admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'email_verified_at' => now(),
                'password' => 'password',
                'plan' => 'paid',
            ]
        );
        $admin->assignRole('admin');

        // Give admin an active premium subscription
        \App\Models\UserSubscription::updateOrCreate(
            ['user_id' => $admin->id, 'status' => 'active'],
            [
                'subscription_plan_id' => $premiumPlan->id,
                'starts_at' => now(),
            ]
        );

        // Create test provider
        $provider = User::updateOrCreate(
            ['email' => 'provider@example.com'],
            [
                'name' => 'Provider Company',
                'email_verified_at' => now(),
                'password' => 'password',
                'plan' => 'paid',
            ]
        );
        $provider->assignRole('provider_admin');

        // Give provider an active premium subscription
        \App\Models\UserSubscription::updateOrCreate(
            ['user_id' => $provider->id, 'status' => 'active'],
            [
                'subscription_plan_id' => $premiumPlan->id,
                'starts_at' => now(),
            ]
        );

        // Create test customers with service requests
        $customers = [
            ['name' => 'John Customer', 'email' => 'john@example.com', 'plan' => 'free'],
            ['name' => 'Sarah Customer', 'email' => 'sarah@example.com', 'plan' => 'paid'],
            ['name' => 'Mike Customer', 'email' => 'mike@example.com', 'plan' => 'paid'],
        ];

        foreach ($customers as $customerData) {
            $customer = User::updateOrCreate(
                ['email' => $customerData['email']],
                [
                    'name' => $customerData['name'],
                    'email_verified_at' => now(),
                    'password' => 'password',
                    'plan' => $customerData['plan'],
                ]
            );
            $customer->assignRole('customer');

            // Give customer an active subscription based on their plan
            $planToUse = $customerData['plan'] === 'paid' ? $premiumPlan : $freePlan;
            \App\Models\UserSubscription::updateOrCreate(
                ['user_id' => $customer->id, 'status' => 'active'],
                [
                    'subscription_plan_id' => $planToUse->id,
                    'starts_at' => now(),
                ]
            );

            // Create service requests for each customer (checking if already exists)
            if (!ServiceRequest::where('customer_id', $customer->id)->where('title', 'Website Development Request')->exists()) {
                ServiceRequest::create([
                    'customer_id' => $customer->id,
                    'title' => 'Website Development Request',
                    'description' => 'Need a professional website for my business with e-commerce functionality.',
                    'status' => 'pending',
                    'latitude' => 24.7136,
                    'longitude' => 46.6753,
                ]);
            }

            if (!ServiceRequest::where('customer_id', $customer->id)->where('title', 'Mobile App Design')->exists()) {
                ServiceRequest::create([
                    'customer_id' => $customer->id,
                    'title' => 'Mobile App Design',
                    'description' => 'Looking for UI/UX design for a fitness tracking mobile application.',
                    'status' => 'accepted',
                    'provider_id' => $provider->id,
                    'latitude' => 24.7236,
                    'longitude' => 46.6853,
                ]);
            }

            if (!ServiceRequest::where('customer_id', $customer->id)->where('title', 'Logo Design')->exists()) {
                ServiceRequest::create([
                    'customer_id' => $customer->id,
                    'title' => 'Logo Design',
                    'description' => 'Simple logo design for a coffee shop brand.',
                    'status' => 'completed',
                    'provider_id' => $provider->id,
                    'latitude' => 24.7036,
                    'longitude' => 46.6653,
                ]);
            }
        }

        // Create additional random service requests if few exist
        if (ServiceRequest::count() < 10) {
            ServiceRequest::factory(10)->create([
                'provider_id' => $provider->id,
            ]);
        }

        $this->command->info('Test data created successfully!');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Provider: provider@example.com / password');
        $this->command->info('Customers: john@example.com, sarah@example.com, mike@example.com / password');
    }
}
