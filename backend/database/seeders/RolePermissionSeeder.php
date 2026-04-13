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
            'request.view.all',
            'request.view.own',
            'request.update.own',
            'request.delete.own',
            'request.view_nearby',
            'user.manage',
            'user.view.own',
            'user.update.own',
            'permission.assign',
            'permission.view',
            'permission.create',
            'permission.update',
            'permission.delete',
            'role.view',
            'role.create',
            'role.update',
            'role.delete',
            'file.upload',
            'file.view.own',
            'file.delete.own',
            'ai.enhance.description',
            'ai.suggest.pricing',
            'analytics.view',
            'analytics.export',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // Create roles and assign permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $admin->givePermissionTo(Permission::where('guard_name', 'sanctum')->get());

        $providerAdmin = Role::firstOrCreate(['name' => 'provider_admin', 'guard_name' => 'sanctum']);
        $providerAdmin->givePermissionTo(Permission::where('guard_name', 'sanctum')->whereIn('name', [
            'request.accept',
            'request.complete',
            'request.view.all',
            'request.view_nearby',
            'permission.assign',
            'user.view.own',
            'user.update.own',
            'file.upload',
            'file.view.own',
            'ai.enhance.description',
            'analytics.view'
        ])->get());

        $providerEmployee = Role::firstOrCreate(['name' => 'provider_employee', 'guard_name' => 'sanctum']);
        $providerEmployee->givePermissionTo(Permission::where('guard_name', 'sanctum')->whereIn('name', [
            'request.accept',
            'request.complete',
            'request.view_nearby',
            'request.view.own',
            'user.view.own',
            'user.update.own'
        ])->get());

        $customer = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'sanctum']);
        $customer->givePermissionTo(Permission::where('guard_name', 'sanctum')->whereIn('name', [
            'request.create',
            'request.view.own',
            'request.update.own',
            'request.delete.own',
            'user.view.own',
            'user.update.own'
        ])->get());
    }
}
