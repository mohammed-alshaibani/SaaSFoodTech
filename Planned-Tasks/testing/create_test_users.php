<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Create test users
$admin = User::create([
    'name' => 'Admin User',
    'email' => 'admin@test.com',
    'password' => Hash::make('password'),
    'plan' => 'free',
]);
$admin->assignRole('admin');

$customer = User::create([
    'name' => 'Customer User',
    'email' => 'customer@test.com',
    'password' => Hash::make('password'),
    'plan' => 'free',
]);
$customer->assignRole('customer');

$provider = User::create([
    'name' => 'Provider Admin',
    'email' => 'provider@test.com',
    'password' => Hash::make('password'),
    'plan' => 'free',
]);
$provider->assignRole('provider_admin');

echo "Test users created successfully!\n";
echo "Admin: admin@test.com / password\n";
echo "Customer: customer@test.com / password\n";
echo "Provider: provider@test.com / password\n";
