<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'request.create',
            'request.accept',
            'request.complete',
            'request.view_all',
            'request.view_nearby',
            'user.manage',
            'permission.assign',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        $providerAdmin = Role::create(['name' => 'provider_admin']);
        $providerAdmin->givePermissionTo(['request.accept', 'request.complete', 'request.view_all', 'request.view_nearby', 'permission.assign']);

        $providerEmployee = Role::create(['name' => 'provider_employee']);
        $providerEmployee->givePermissionTo(['request.accept', 'request.complete', 'request.view_nearby']);

        $customer = Role::create(['name' => 'customer']);
        $customer->givePermissionTo(['request.create']);

        // Create a default admin user
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'plan' => 'paid',
        ]);
        $user->assignRole($admin);

        // Create a default customer
        $customerUser = User::create([
            'name' => 'Customer User',
            'email' => 'customer@test.com',
            'password' => bcrypt('password'),
            'plan' => 'free',
        ]);
        $customerUser->assignRole($customer);
    }
}
