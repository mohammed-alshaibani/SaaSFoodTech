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
        $permissions = [
            // Service Requests
            ['name' => 'request.create', 'description' => 'Create new service requests', 'group' => 'Service Requests', 'category_id' => 1, 'is_system' => true],
            ['name' => 'request.view.own', 'description' => 'View own service requests', 'group' => 'Service Requests', 'category_id' => 1, 'is_system' => true],
            ['name' => 'request.view.all', 'description' => 'View all service requests', 'group' => 'Service Requests', 'category_id' => 1, 'is_system' => true],
            ['name' => 'request.view_nearby', 'description' => 'View nearby service requests', 'group' => 'Service Requests', 'category_id' => 1, 'is_system' => true],
            ['name' => 'request.accept', 'description' => 'Accept service requests', 'group' => 'Service Requests', 'category_id' => 1, 'is_system' => true],
            ['name' => 'request.complete', 'description' => 'Mark service requests as completed', 'group' => 'Service Requests', 'category_id' => 1, 'is_system' => true],
            ['name' => 'request.update.own', 'description' => 'Update own service requests', 'group' => 'Service Requests', 'category_id' => 1, 'is_system' => true],
            ['name' => 'request.update.any', 'description' => 'Update any service requests', 'group' => 'Service Requests', 'category_id' => 1, 'is_system' => true],
            ['name' => 'request.delete.own', 'description' => 'Delete own service requests', 'group' => 'Service Requests', 'category_id' => 1, 'is_system' => true],
            ['name' => 'request.delete.any', 'description' => 'Delete any service requests', 'group' => 'Service Requests', 'category_id' => 1, 'is_system' => true],

            // User Management
            ['name' => 'user.create', 'description' => 'Create new users', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'user.view.own', 'description' => 'View own user profile', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'user.view.any', 'description' => 'View any user profile', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'user.update.own', 'description' => 'Update own user profile', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'user.update.any', 'description' => 'Update any user profile', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'user.delete.any', 'description' => 'Delete any user', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'user.assign.roles', 'description' => 'Assign roles to users', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'user.grant.permissions', 'description' => 'Grant direct permissions to users', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],

            // Role & Permission Management
            ['name' => 'role.create', 'description' => 'Create new roles', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'role.view', 'description' => 'View roles and permissions', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'role.update', 'description' => 'Update existing roles', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'role.delete', 'description' => 'Delete roles', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'permission.create', 'description' => 'Create new permissions', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'permission.update', 'description' => 'Update existing permissions', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'permission.delete', 'description' => 'Delete permissions', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],
            ['name' => 'role.hierarchy.manage', 'description' => 'Manage role hierarchy', 'group' => 'User Management', 'category_id' => 2, 'is_system' => true],

            // System Administration
            ['name' => 'system.monitor', 'description' => 'Access system monitoring and health checks', 'group' => 'System Administration', 'category_id' => 3, 'is_system' => true],
            ['name' => 'system.logs', 'description' => 'View system logs and audit trails', 'group' => 'System Administration', 'category_id' => 3, 'is_system' => true],
            ['name' => 'system.config', 'description' => 'Modify system configuration', 'group' => 'System Administration', 'category_id' => 3, 'is_system' => true],
            ['name' => 'system.backup', 'description' => 'Create and restore system backups', 'group' => 'System Administration', 'category_id' => 3, 'is_system' => true],

            // AI Features
            ['name' => 'ai.enhance.description', 'description' => 'Use AI to enhance request descriptions', 'group' => 'AI Features', 'category_id' => 4, 'is_system' => true],
            ['name' => 'ai.categorize.request', 'description' => 'Use AI to categorize service requests', 'group' => 'AI Features', 'category_id' => 4, 'is_system' => true],
            ['name' => 'ai.suggest.pricing', 'description' => 'Use AI to suggest pricing for requests', 'group' => 'AI Features', 'category_id' => 4, 'is_system' => true],

            // Subscription Management
            ['name' => 'subscription.view', 'description' => 'View subscription plans and usage', 'group' => 'Subscription Management', 'category_id' => 5, 'is_system' => true],
            ['name' => 'subscription.upgrade', 'description' => 'Upgrade subscription plans', 'group' => 'Subscription Management', 'category_id' => 5, 'is_system' => true],
            ['name' => 'subscription.manage.any', 'description' => 'Manage any user\'s subscription', 'group' => 'Subscription Management', 'category_id' => 5, 'is_system' => true],

            // File Management
            ['name' => 'file.upload', 'description' => 'Upload files to requests', 'group' => 'File Management', 'category_id' => 6, 'is_system' => true],
            ['name' => 'file.view.own', 'description' => 'View own uploaded files', 'group' => 'File Management', 'category_id' => 6, 'is_system' => true],
            ['name' => 'file.view.any', 'description' => 'View any uploaded files', 'group' => 'File Management', 'category_id' => 6, 'is_system' => true],
            ['name' => 'file.delete.own', 'description' => 'Delete own uploaded files', 'group' => 'File Management', 'category_id' => 6, 'is_system' => true],
            ['name' => 'file.delete.any', 'description' => 'Delete any uploaded files', 'group' => 'File Management', 'category_id' => 6, 'is_system' => true],

            // Monitoring & Analytics
            ['name' => 'analytics.view', 'description' => 'View system analytics and reports', 'group' => 'Monitoring & Analytics', 'category_id' => 7, 'is_system' => true],
            ['name' => 'analytics.export', 'description' => 'Export analytics data', 'group' => 'Monitoring & Analytics', 'category_id' => 7, 'is_system' => true],
        ];

        foreach ($permissions as $permission) {
            $createdPermission = Permission::create([
                'name' => $permission['name'],
                'description' => $permission['description'],
                'group' => $permission['group'],
                'category_id' => $permission['category_id'],
                'is_system' => $permission['is_system'],
                'guard_name' => 'api',
            ]);

            // Add scoped permissions for location-based access
            if (in_array($permission['name'], ['request.view_nearby', 'request.accept'])) {
                $createdPermission->scopes()->create([
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
            ['name' => 'Super Admin'],
            ['name' => 'Admin'],
            ['name' => 'Provider Admin'],
            ['name' => 'Provider'],
            ['name' => 'Customer'],
            ['name' => 'Guest'],
        ];

        foreach ($roles as $role) {
            Role::create([
                'name' => $role['name'],
                'guard_name' => 'api',
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
        $roles['Super Admin']->givePermissionTo($permissions->values());

        // Admin - Most permissions except role hierarchy
        $adminPermissions = $permissions->except(['role.hierarchy.manage']);
        $roles['Admin']->givePermissionTo($adminPermissions->values());

        // Provider Admin - Provider management permissions
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
        $roles['Provider Admin']->givePermissionTo(
            collect($providerAdminPermissions)->map(fn($name) => $permissions[$name])->filter()
        );

        // Provider - Basic provider permissions
        $providerPermissions = [
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
            'subscription.upgrade',
        ];
        $roles['Provider']->givePermissionTo(
            collect($providerPermissions)->map(fn($name) => $permissions[$name])->filter()
        );

        // Customer - Basic customer permissions
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
        $roles['Customer']->givePermissionTo(
            collect($customerPermissions)->map(fn($name) => $permissions[$name])->filter()
        );

        // Guest - Minimal permissions
        $guestPermissions = [
            'user.view.own',
            'user.update.own',
            'subscription.view',
        ];
        $roles['Guest']->givePermissionTo(
            collect($guestPermissions)->map(fn($name) => $permissions[$name])->filter()
        );
    }

    /**
     * Create role hierarchy.
     */
    private function createRoleHierarchy(): void
    {
        $roles = Role::all()->keyBy('name');

        // Super Admin > Admin
        $roles['Super Admin']->addChildRole($roles['Admin']);

        // Admin > Provider Admin
        $roles['Admin']->addChildRole($roles['Provider Admin']);

        // Provider Admin > Provider
        $roles['Provider Admin']->addChildRole($roles['Provider']);

        // Provider > Customer
        $roles['Provider']->addChildRole($roles['Customer']);

        // Customer > Guest
        $roles['Customer']->addChildRole($roles['Guest']);
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
                if (isset($roles['Admin'])) {
                    $user->assignRole($roles['Admin']);
                }
            } elseif ($user->plan === 'premium' || str_contains($user->email, 'provider')) {
                if (isset($roles['Provider'])) {
                    $user->assignRole($roles['Provider']);
                }
            } else {
                if (isset($roles['Customer'])) {
                    $user->assignRole($roles['Customer']);
                }
            }
        }

        // Create a super admin user if none exists
        if (!User::role('Super Admin')->exists() && isset($roles['Super Admin'])) {
            $superAdmin = User::create([
                'name' => 'Super Admin',
                'email' => 'superadmin@saasfoodtech.com',
                'password' => bcrypt('password123'),
                'plan' => 'enterprise',
            ]);
            $superAdmin->assignRole($roles['Super Admin']);
        }
    }
}
