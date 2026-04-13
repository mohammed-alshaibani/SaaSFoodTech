<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\PermissionCategory;
use App\Models\PermissionScope;
use App\Models\Role;
use App\Models\User;

class AdvancedRbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        // Clear Spatie permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Clear existing data
        Permission::query()->delete();
        Role::query()->delete();
        PermissionCategory::query()->delete();
        PermissionScope::query()->delete();

        DB::table('role_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('role_hierarchy')->delete();
        DB::table('user_permissions')->delete();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $this->createPermissionCategories();
        $this->createPermissions();
        $this->createRoles();
        $this->assignPermissionsToRoles();
        $this->createRoleHierarchy();
        $this->assignRolesToUsers();
    }

    /**
     * Create permission categories.
     */
    private function createPermissionCategories(): void
    {
        $categories = [
            ['name' => 'Service Requests', 'description' => 'Permissions related to service request management', 'icon' => 'clipboard-list', 'sort_order' => 1],
            ['name' => 'User Management', 'description' => 'Permissions related to user and role management', 'icon' => 'users', 'sort_order' => 2],
            ['name' => 'System Administration', 'description' => 'System-level administrative permissions', 'icon' => 'cog', 'sort_order' => 3],
            ['name' => 'AI Features', 'description' => 'Permissions for AI-powered features', 'icon' => 'brain', 'sort_order' => 4],
            ['name' => 'Subscription Management', 'description' => 'Permissions for subscription and billing', 'icon' => 'credit-card', 'sort_order' => 5],
            ['name' => 'File Management', 'description' => 'Permissions for file uploads and attachments', 'icon' => 'file', 'sort_order' => 6],
            ['name' => 'Monitoring & Analytics', 'description' => 'Permissions for system monitoring and analytics', 'icon' => 'chart-bar', 'sort_order' => 7],
        ];

        foreach ($categories as $category) {
            PermissionCategory::create($category);
        }
    }

    /**
     * Create permissions.
     */
    private function createPermissions(): void
    {
        $categoryMap = PermissionCategory::all()->pluck('id', 'name')->toArray();

        $permissions = [
            // Service Requests
            ['name' => 'request.create', 'description' => 'Create new service requests', 'group' => 'Service Requests', 'category' => 'Service Requests', 'is_system' => true],
            ['name' => 'request.view.own', 'description' => 'View own service requests', 'group' => 'Service Requests', 'category' => 'Service Requests', 'is_system' => true],
            ['name' => 'request.view.all', 'description' => 'View all service requests', 'group' => 'Service Requests', 'category' => 'Service Requests', 'is_system' => true],
            ['name' => 'request.view_nearby', 'description' => 'View nearby service requests', 'group' => 'Service Requests', 'category' => 'Service Requests', 'is_system' => true],
            ['name' => 'request.accept', 'description' => 'Accept service requests', 'group' => 'Service Requests', 'category' => 'Service Requests', 'is_system' => true],
            ['name' => 'request.complete', 'description' => 'Mark service requests as completed', 'group' => 'Service Requests', 'category' => 'Service Requests', 'is_system' => true],
            ['name' => 'request.update.own', 'description' => 'Update own service requests', 'group' => 'Service Requests', 'category' => 'Service Requests', 'is_system' => true],
            ['name' => 'request.update.any', 'description' => 'Update any service requests', 'group' => 'Service Requests', 'category' => 'Service Requests', 'is_system' => true],
            ['name' => 'request.delete.own', 'description' => 'Delete own service requests', 'group' => 'Service Requests', 'category' => 'Service Requests', 'is_system' => true],
            ['name' => 'request.delete.any', 'description' => 'Delete any service requests', 'group' => 'Service Requests', 'category' => 'Service Requests', 'is_system' => true],

            // User Management
            ['name' => 'user.create', 'description' => 'Create new users', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'user.view.own', 'description' => 'View own user profile', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'user.view.any', 'description' => 'View any user profile', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'user.update.own', 'description' => 'Update own user profile', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'user.update.any', 'description' => 'Update any user profile', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'user.delete.any', 'description' => 'Delete any user', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'user.assign.roles', 'description' => 'Assign roles to users', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'user.grant.permissions', 'description' => 'Grant direct permissions to users', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],

            // Role & Permission Management
            ['name' => 'role.create', 'description' => 'Create new roles', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'role.view', 'description' => 'View roles and permissions', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'role.update', 'description' => 'Update existing roles', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'role.delete', 'description' => 'Delete roles', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'permission.create', 'description' => 'Create new permissions', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'permission.update', 'description' => 'Update existing permissions', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'permission.delete', 'description' => 'Delete permissions', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],
            ['name' => 'role.hierarchy.manage', 'description' => 'Manage role hierarchy', 'group' => 'User Management', 'category' => 'User Management', 'is_system' => true],

            // System Administration
            ['name' => 'system.monitor', 'description' => 'Access system monitoring and health checks', 'group' => 'System Administration', 'category' => 'System Administration', 'is_system' => true],
            ['name' => 'system.logs', 'description' => 'View system logs and audit trails', 'group' => 'System Administration', 'category' => 'System Administration', 'is_system' => true],
            ['name' => 'system.config', 'description' => 'Modify system configuration', 'group' => 'System Administration', 'category' => 'System Administration', 'is_system' => true],
            ['name' => 'system.backup', 'description' => 'Create and restore system backups', 'group' => 'System Administration', 'category' => 'System Administration', 'is_system' => true],

            // AI Features
            ['name' => 'ai.enhance.description', 'description' => 'Use AI to enhance request descriptions', 'group' => 'AI Features', 'category' => 'AI Features', 'is_system' => true],
            ['name' => 'ai.categorize.request', 'description' => 'Use AI to categorize service requests', 'group' => 'AI Features', 'category' => 'AI Features', 'is_system' => true],
            ['name' => 'ai.suggest.pricing', 'description' => 'Use AI to suggest pricing for requests', 'group' => 'AI Features', 'category' => 'AI Features', 'is_system' => true],

            // Subscription Management
            ['name' => 'subscription.view', 'description' => 'View subscription plans and usage', 'group' => 'Subscription Management', 'category' => 'Subscription Management', 'is_system' => true],
            ['name' => 'subscription.upgrade', 'description' => 'Upgrade subscription plans', 'group' => 'Subscription Management', 'category' => 'Subscription Management', 'is_system' => true],
            ['name' => 'subscription.manage.any', 'description' => 'Manage any user\'s subscription', 'group' => 'Subscription Management', 'category' => 'Subscription Management', 'is_system' => true],

            // File Management
            ['name' => 'file.upload', 'description' => 'Upload files to requests', 'group' => 'File Management', 'category' => 'File Management', 'is_system' => true],
            ['name' => 'file.view.own', 'description' => 'View own uploaded files', 'group' => 'File Management', 'category' => 'File Management', 'is_system' => true],
            ['name' => 'file.view.any', 'description' => 'View any uploaded files', 'group' => 'File Management', 'category' => 'File Management', 'is_system' => true],
            ['name' => 'file.delete.own', 'description' => 'Delete own uploaded files', 'group' => 'File Management', 'category' => 'File Management', 'is_system' => true],
            ['name' => 'file.delete.any', 'description' => 'Delete any uploaded files', 'group' => 'File Management', 'category' => 'File Management', 'is_system' => true],

            // Monitoring & Analytics
            ['name' => 'analytics.view', 'description' => 'View system analytics and reports', 'group' => 'Monitoring & Analytics', 'category' => 'Monitoring & Analytics', 'is_system' => true],
            ['name' => 'analytics.export', 'description' => 'Export analytics data', 'group' => 'Monitoring & Analytics', 'category' => 'Monitoring & Analytics', 'is_system' => true],
        ];

        foreach ($permissions as $permission) {
            $createdPermission = Permission::create([
                'name' => $permission['name'],
                'description' => $permission['description'],
                'group' => $permission['group'],
                'category_id' => $categoryMap[$permission['category']] ?? null,
                'is_system' => $permission['is_system'],
                'guard_name' => 'sanctum',
            ]);

            // Add scoped permissions for location-based access
            if (in_array($permission['name'], ['request.view_nearby', 'request.accept'])) {
                $createdPermission->permissionScopes()->create([
                    'scope_type' => 'location',
                    'scope_values' => ['max_distance' => 50], // 50km radius
                ]);
            }
        }
    }

    /**
     * Create roles.
     */
    private function createRoles(): void
    {
        $roles = [
            ['name' => 'super_admin'],
            ['name' => 'admin'],
            ['name' => 'provider_admin'],
            ['name' => 'provider_employee'],
            ['name' => 'customer'],
            ['name' => 'guest'],
        ];

        foreach ($roles as $role) {
            Role::create([
                'name' => $role['name'],
                'guard_name' => 'sanctum',
            ]);
        }
    }

    /**
     * Assign permissions to roles.
     */
    private function assignPermissionsToRoles(): void
    {
        $roles = Role::all()->keyBy('name');
        $permissions = Permission::all()->keyBy('name');

        // Super Admin - All permissions
        if (isset($roles['super_admin'])) {
            $roles['super_admin']->givePermissionTo($permissions->values());
        }

        // Admin - Most permissions except role hierarchy
        if (isset($roles['admin'])) {
            $adminPermissions = $permissions->except(['role.hierarchy.manage']);
            $roles['admin']->givePermissionTo($adminPermissions->values());
        }

        // Provider Admin - Provider management permissions
        if (isset($roles['provider_admin'])) {
            $providerAdminPermissions = [
                'request.view.all',
                'request.view_nearby',
                'request.accept',
                'request.complete',
                'user.view.any',
                'user.assign.roles',
                'role.view',
                'subscription.manage.any',
                'file.view.any',
                'file.delete.any',
                'ai.enhance.description',
                'ai.categorize.request',
                'ai.suggest.pricing',
                'analytics.view',
                'analytics.export',
            ];
            $roles['provider_admin']->givePermissionTo(
                collect($providerAdminPermissions)->map(fn($name) => $permissions[$name] ?? null)->filter()
            );
        }

        // Provider Employee - Task execution permissions
        if (isset($roles['provider_employee'])) {
            $providerEmployeePermissions = [
                'request.view_nearby',
                'request.accept',
                'request.complete',
                'request.view.own',
                'request.update.own',
                'user.view.own',
                'user.update.own',
                'file.upload',
                'file.view.own',
                'file.delete.own',
                'ai.enhance.description',
                'ai.categorize.request',
                'ai.suggest.pricing',
                'subscription.view',
            ];
            $roles['provider_employee']->givePermissionTo(
                collect($providerEmployeePermissions)->map(fn($name) => $permissions[$name] ?? null)->filter()
            );
        }

        // Customer - Basic customer permissions
        if (isset($roles['customer'])) {
            $customerPermissions = [
                'request.create',
                'request.view.own',
                'request.update.own',
                'request.delete.own',
                'user.view.own',
                'user.update.own',
                'file.upload',
                'file.view.own',
                'file.delete.own',
                'ai.enhance.description',
                'ai.suggest.pricing',
                'subscription.view',
                'subscription.upgrade',
            ];
            $roles['customer']->givePermissionTo(
                collect($customerPermissions)->map(fn($name) => $permissions[$name] ?? null)->filter()
            );
        }

        // Guest - Minimal permissions
        if (isset($roles['guest'])) {
            $guestPermissions = [
                'user.view.own',
                'user.update.own',
                'subscription.view',
            ];
            $roles['guest']->givePermissionTo(
                collect($guestPermissions)->map(fn($name) => $permissions[$name] ?? null)->filter()
            );
        }
    }

    /**
     * Create role hierarchy.
     */
    private function createRoleHierarchy(): void
    {
        $roles = Role::all()->keyBy('name');

        // Hierarchy logic using slug names
        if (isset($roles['super_admin'], $roles['admin'])) {
            $roles['super_admin']->addChildRole($roles['admin']);
        }
        if (isset($roles['admin'], $roles['provider_admin'])) {
            $roles['admin']->addChildRole($roles['provider_admin']);
        }
        if (isset($roles['provider_admin'], $roles['provider_employee'])) {
            $roles['provider_admin']->addChildRole($roles['provider_employee']);
        }
    }

    /**
     * Assign roles to existing users.
     */
    private function assignRolesToUsers(): void
    {
        $roles = Role::all()->keyBy('name');

        // Get existing users and assign default roles
        $users = User::all();

        foreach ($users as $user) {
            // Assign role based on existing plan or email
            if (str_contains($user->email, 'admin')) {
                if (isset($roles['admin'])) {
                    $user->assignRole($roles['admin']);
                }
            } elseif ($user->plan === 'premium' || str_contains($user->email, 'provider')) {
                if (isset($roles['provider_admin'])) {
                    $user->assignRole($roles['provider_admin']);
                }
            } else {
                if (isset($roles['customer'])) {
                    $user->assignRole($roles['customer']);
                }
            }
        }

        // Create a super admin user if none exists
        if (!User::role('super_admin')->exists() && isset($roles['super_admin'])) {
            $superAdmin = User::create([
                'name' => 'Super Admin',
                'email' => 'superadmin@saasfoodtech.com',
                'password' => 'password123',
                'plan' => 'enterprise',
            ]);
            $superAdmin->assignRole($roles['super_admin']);
        }
    }
}
