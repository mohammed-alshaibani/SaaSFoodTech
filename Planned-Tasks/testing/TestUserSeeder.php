<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'plan' => 'free',
        ]);
        $admin->assignRole('admin');

        // Create customer user
        $customer = User::create([
            'name' => 'Customer User',
            'email' => 'customer@test.com',
            'password' => Hash::make('password'),
            'plan' => 'free',
        ]);
        $customer->assignRole('customer');

        // Create provider admin user
        $provider = User::create([
            'name' => 'Provider Admin',
            'email' => 'provider@test.com',
            'password' => Hash::make('password'),
            'plan' => 'free',
        ]);
        $provider->assignRole('provider_admin');

        $this->command->info('Test users created successfully!');
        $this->command->info('Admin: admin@test.com / password');
        $this->command->info('Customer: customer@test.com / password');
        $this->command->info('Provider: provider@test.com / password');
    }
}
