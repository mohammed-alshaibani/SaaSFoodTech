<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    echo "Creating test users...\n";

    // Create admin user
    $admin = User::where('email', 'admin@test.com')->first();
    if (!$admin) {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'plan' => 'paid',
        ]);
        $admin->assignRole('admin');
        echo "✅ Admin user created: admin@test.com\n";
    } else {
        $admin->assignRole('admin');
        echo "✅ Admin user already exists: admin@test.com\n";
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
        echo "✅ Customer user created: customer@test.com\n";
    } else {
        $customer->assignRole('customer');
        echo "✅ Customer user already exists: customer@test.com\n";
    }

    // Create provider user
    $provider = User::where('email', 'provider@test.com')->first();
    if (!$provider) {
        $provider = User::create([
            'name' => 'Provider User',
            'email' => 'provider@test.com',
            'password' => Hash::make('password'),
            'plan' => 'free',
        ]);
        $provider->assignRole('provider_admin');
        echo "✅ Provider user created: provider@test.com\n";
    } else {
        $provider->assignRole('provider_admin');
        echo "✅ Provider user already exists: provider@test.com\n";
    }

    echo "\n🎉 Test users ready!\n";
    echo "Login credentials:\n";
    echo "Admin:   admin@test.com / password\n";
    echo "Customer: customer@test.com / password\n";
    echo "Provider: provider@test.com / password\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
