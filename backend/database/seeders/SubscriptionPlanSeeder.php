<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'free',
                'display_name' => 'Free',
                'description' => 'Perfect for getting started with basic features',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'features' => [
                    'max_requests_per_month' => 3,
                    'basic_support' => false,
                    'priority_support' => false,
                ],
                'limits' => [
                    'requests_per_month' => 3,
                    'attachments_per_request' => 5,
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'basic',
                'display_name' => 'Basic',
                'description' => 'Great for small teams and growing businesses',
                'price' => 29.99,
                'billing_cycle' => 'monthly',
                'features' => [
                    'max_requests_per_month' => 50,
                    'basic_support' => true,
                    'priority_support' => false,
                ],
                'limits' => [
                    'requests_per_month' => 50,
                    'attachments_per_request' => 10,
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'premium',
                'display_name' => 'Premium',
                'description' => 'Advanced features for power users',
                'price' => 79.99,
                'billing_cycle' => 'monthly',
                'features' => [
                    'max_requests_per_month' => 200,
                    'basic_support' => true,
                    'priority_support' => true,
                    'ai_enhancement' => true,
                ],
                'limits' => [
                    'requests_per_month' => 200,
                    'attachments_per_request' => 20,
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'enterprise',
                'display_name' => 'Enterprise',
                'description' => 'Complete solution for large organizations',
                'price' => 199.99,
                'billing_cycle' => 'monthly',
                'features' => [
                    'max_requests_per_month' => 'unlimited',
                    'basic_support' => true,
                    'priority_support' => true,
                    'ai_enhancement' => true,
                    'api_access' => true,
                    'custom_integrations' => true,
                ],
                'limits' => [
                    'requests_per_month' => 'unlimited',
                    'attachments_per_request' => 50,
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }

        $this->command->info('Subscription plans seeded successfully!');
    }
}
