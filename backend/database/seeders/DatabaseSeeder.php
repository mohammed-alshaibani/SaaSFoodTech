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
        ]);

        // Create test admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'plan' => 'paid',
        ]);
        $admin->assignRole('admin');

        // Create test provider
        $provider = User::create([
            'name' => 'Provider Company',
            'email' => 'provider@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'plan' => 'paid',
        ]);
        $provider->assignRole('provider_admin');

        // Create test customers with service requests
        $customers = [
            ['name' => 'John Customer', 'email' => 'john@example.com', 'plan' => 'free'],
            ['name' => 'Sarah Customer', 'email' => 'sarah@example.com', 'plan' => 'paid'],
            ['name' => 'Mike Customer', 'email' => 'mike@example.com', 'plan' => 'paid'],
        ];

        foreach ($customers as $customerData) {
            $customer = User::create([
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'plan' => $customerData['plan'],
            ]);
            $customer->assignRole('customer');

            // Create service requests for each customer
            ServiceRequest::create([
                'customer_id' => $customer->id,
                'title' => 'Website Development Request',
                'description' => 'Need a professional website for my business with e-commerce functionality.',
                'status' => 'pending',
                'latitude' => 24.7136,
                'longitude' => 46.6753,
            ]);

            ServiceRequest::create([
                'customer_id' => $customer->id,
                'title' => 'Mobile App Design',
                'description' => 'Looking for UI/UX design for a fitness tracking mobile application.',
                'status' => 'accepted',
                'provider_id' => $provider->id,
                'latitude' => 24.7236,
                'longitude' => 46.6853,
            ]);

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

        // Create additional random service requests
        ServiceRequest::factory(10)->create([
            'provider_id' => $provider->id,
        ]);

        $this->command->info('Test data created successfully!');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Provider: provider@example.com / password');
        $this->command->info('Customers: john@example.com, sarah@example.com, mike@example.com / password');
    }
}
