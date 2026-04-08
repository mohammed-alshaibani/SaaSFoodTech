<?php

require __DIR__.'/vendor/autoload.php';

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    // Create admin user
    $admin = User::where('email', 'admin@test.com')->first();
    if (!$admin) {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'plan' => 'free',
        ]);
        $admin->assignRole('admin');
        echo "Admin user created: admin@test.com\n";
    } else {
        echo "Admin user already exists: admin@test.com\n";
    }

    // Create customer user
    $customer = User::where('email', 'customer@test.com')->first();
    if (!$customer) {
        $customer = User::create([
            'name' => 'Customer User',
            'email' => 'customer@test.com',
            'password' => Hash::make('password'),
            'plan' => 'free',
        ]);
        $customer->assignRole('customer');
        echo "Customer user created: customer@test.com\n";
    } else {
        echo "Customer user already exists: customer@test.com\n";
    }

    // Create provider admin user
    $provider = User::where('email', 'provider@test.com')->first();
    if (!$provider) {
        $provider = User::create([
            'name' => 'Provider Admin',
            'email' => 'provider@test.com',
            'password' => Hash::make('password'),
            'plan' => 'free',
        ]);
        $provider->assignRole('provider_admin');
        echo "Provider user created: provider@test.com\n";
    } else {
        echo "Provider user already exists: provider@test.com\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
