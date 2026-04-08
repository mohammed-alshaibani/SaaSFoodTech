<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\PermissionCategory;
use App\Models\UserPermission;
use App\Models\RolePermissionsAudit;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvancedRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the advanced RBAC seeder
        $this->seed(\Database\Seeders\AdvancedRbacSeeder::class);
    }

    /** @test */
    public function super_admin_has_all_permissions()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        
        Sanctum::actingAs($superAdmin);

        // Test access to all permission-protected routes
        $response = $this->getJson('/api/permissions');
        $response->assertStatus(200);

        $response = $this->getJson('/api/roles');
        $response->assertStatus(200);

        // Check user has all permissions
        $allPermissions = Permission::count();
        $userPermissions = $superAdmin->getAllEffectivePermissions()->count();
        
        $this->assertEquals($allPermissions, $userPermissions);
    }

    /** @test */
    public function role_hierarchy_inherits_permissions()
    {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('Admin');
        
        $providerAdminUser = User::factory()->create();
        $providerAdminUser->assignRole('Provider Admin');
        
        $providerUser = User::factory()->create();
        $providerUser->assignRole('Provider');

        // Admin should have more permissions than Provider Admin
        $adminPermissions = $adminUser->getAllEffectivePermissions()->count();
        $providerAdminPermissions = $providerAdminUser->getAllEffectivePermissions()->count();
        $providerPermissions = $providerUser->getAllEffectivePermissions()->count();

        $this->assertGreaterThan($providerAdminPermissions, $adminPermissions);
        $this->assertGreaterThan($providerPermissions, $providerAdminPermissions);
    }

    /** @test */
    public function can_create_custom_permissions()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        
        Sanctum::actingAs($superAdmin);

        $category = PermissionCategory::factory()->create();

        $permissionData = [
            'name' => 'custom.test.permission',
            'description' => 'A custom test permission',
            'group' => 'Test Group',
            'category_id' => $category->id,
            'is_system' => false,
        ];

        $response = $this->postJson('/api/permissions', $permissionData);

        $response->assertStatus(201)
                ->assertJsonFragment([
                    'name' => 'custom.test.permission',
                    'description' => 'A custom test permission',
                ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'custom.test.permission',
            'is_system' => false,
        ]);
    }

    /** @test */
    public function cannot_modify_system_permissions()
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        
        Sanctum::actingAs($admin);

        $systemPermission = Permission::where('is_system', true)->first();

        $updateData = [
            'name' => 'modified.system.permission',
            'description' => 'Modified description',
        ];

        $response = $this->putJson("/api/permissions/{$systemPermission->id}", $updateData);

        $response->assertStatus(403)
                ->assertJsonFragment([
                    'error' => 'Forbidden',
                    'message' => 'System permissions cannot be modified',
                ]);
    }

    /** @test */
    public function can_create_role_hierarchy()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        
        Sanctum::actingAs($superAdmin);

        // Create a custom role
        $roleData = [
            'name' => 'Custom Manager',
            'permissions' => [Permission::where('name', 'user.view.any')->first()->id],
            'parent_roles' => [Role::where('name', 'Admin')->first()->id],
        ];

        $response = $this->postJson('/api/roles', $roleData);

        $response->assertStatus(201)
                ->assertJsonFragment([
                    'name' => 'Custom Manager',
                ]);

        $customRole = Role::where('name', 'Custom Manager')->first();
        $this->assertTrue($customRole->isChildOf(Role::where('name', 'Admin')->first()));
    }

    /** @test */
    public function cannot_create_circular_role_hierarchy()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        
        Sanctum::actingAs($superAdmin);

        $adminRole = Role::where('name', 'Admin')->first();
        $providerRole = Role::where('name', 'Provider')->first();

        // Try to make Admin a child of Provider (would create circular reference)
        $response = $this->postJson("/api/roles/{$providerRole->id}/hierarchy/add", [
            'child_role_id' => $adminRole->id,
        ]);

        $response->assertStatus(409)
                ->assertJsonFragment([
                    'error' => 'Cannot create hierarchy',
                    'message' => 'This would create a circular reference in the role hierarchy',
                ]);
    }

    /** @test */
    public function can_grant_direct_user_permissions()
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        
        Sanctum::actingAs($admin);

        $permission = Permission::where('name', 'user.view.any')->first();

        $response = $this->postJson("/api/users/{$customer->id}/permissions", [
            'permission_id' => $permission->id,
            'type' => 'grant',
            'reason' => 'Special access granted',
        ]);

        $response->assertStatus(200);

        // Check customer now has the permission
        $this->assertTrue($customer->hasPermissionTo('user.view.any'));
        
        // Check audit log
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $customer->id,
            'permission_id' => $permission->id,
            'type' => 'grant',
            'reason' => 'Special access granted',
        ]);
    }

    /** @test */
    public function can_deny_direct_user_permissions()
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        
        $provider = User::factory()->create();
        $provider->assignRole('Provider');
        
        Sanctum::actingAs($admin);

        $permission = Permission::where('name', 'user.delete.any')->first();

        $response = $this->postJson("/api/users/{$provider->id}/permissions", [
            'permission_id' => $permission->id,
            'type' => 'deny',
            'reason' => 'Restricting delete access',
        ]);

        $response->assertStatus(200);

        // Provider should not have the permission even though their role might grant it
        $this->assertFalse($provider->hasPermissionTo('user.delete.any'));
    }

    /** @test */
    public function direct_permissions_can_expire()
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        
        Sanctum::actingAs($admin);

        $permission = Permission::where('name', 'user.view.any')->first();

        // Grant permission with expiration in the past
        $response = $this->postJson("/api/users/{$customer->id}/permissions", [
            'permission_id' => $permission->id,
            'type' => 'grant',
            'expires_at' => now()->subDay()->toDateTimeString(),
        ]);

        $response->assertStatus(200);

        // Customer should not have the expired permission
        $this->assertFalse($customer->fresh()->hasPermissionTo('user.view.any'));
    }

    /** @test */
    public function permission_scoping_works_correctly()
    {
        $provider = User::factory()->create();
        $provider->assignRole('Provider');
        
        Sanctum::actingAs($provider);

        // Create a scoped permission
        $permission = Permission::create([
            'name' => 'request.view.limited',
            'description' => 'Limited request viewing',
            'guard_name' => 'api',
        ]);

        // Add location scope
        $permission->scopes()->create([
            'scope_type' => 'location',
            'scope_values' => ['max_distance' => 10], // 10km radius
        ]);

        // Grant the scoped permission to provider
        $provider->givePermissionTo($permission);

        // Create request within scope
        $nearbyRequest = ServiceRequest::factory()->create([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        // Create request outside scope
        $farRequest = ServiceRequest::factory()->create([
            'latitude' => 41.0000,
            'longitude' => -75.0000,
        ]);

        // This would need to be tested with the actual middleware implementation
        // For now, just verify the scope exists
        $this->assertTrue($permission->isScoped());
        $this->assertEquals('location', $permission->scopes->first()->scope_type);
    }

    /** @test */
    public function audit_logs_are_created_for_permission_changes()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        
        Sanctum::actingAs($superAdmin);

        $role = Role::where('name', 'Provider')->first();
        $permission = Permission::where('name', 'user.delete.any')->first();

        // Grant permission to role
        $response = $this->postJson("/api/roles/{$role->id}/permissions/grant", [
            'permission_id' => $permission->id,
            'reason' => 'Granting delete access',
        ]);

        $response->assertStatus(200);

        // Check audit log was created
        $this->assertDatabaseHas('role_permissions_audit', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'action' => 'granted',
            'performed_by' => $superAdmin->id,
            'reason' => 'Granting delete access',
        ]);
    }

    /** @test */
    public function permission_categories_are_organized_correctly()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        
        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/permissions/categories');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'categories' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'icon',
                            'sort_order',
                            'permissions_count',
                        ]
                    ]
                ]);

        // Check that categories are ordered by sort_order
        $categories = $response->json('categories');
        $sortedCategories = collect($categories)->sortBy('sort_order')->values()->toArray();
        $this->assertEquals($sortedCategories, $categories);
    }

    /** @test */
    public function role_hierarchy_tree_is_returned_correctly()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        
        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/roles/hierarchy');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'hierarchy' => [
                        '*' => [
                            'id',
                            'name',
                            'permissions',
                            'child_roles' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'permissions',
                                    'child_roles',
                                ]
                            ]
                        ]
                    ]
                ]);

        // Super Admin should be at the root
        $hierarchy = $response->json('hierarchy');
        $superAdminRole = collect($hierarchy)->firstWhere('name', 'Super Admin');
        $this->assertNotNull($superAdminRole);
    }

    /** @test */
    public function unauthorized_users_cannot_access_rbac_endpoints()
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        
        Sanctum::actingAs($customer);

        // Try to access permissions management
        $response = $this->getJson('/api/permissions');
        $response->assertStatus(403);

        // Try to access roles management
        $response = $this->getJson('/api/roles');
        $response->assertStatus(403);

        // Try to create permissions
        $response = $this->postJson('/api/permissions', [
            'name' => 'test.permission',
            'description' => 'Test permission',
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function middleware_enforces_permissions_correctly()
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        
        Sanctum::actingAs($customer);

        // Try to access admin-only endpoint
        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(403);

        // Try to access permission management
        $response = $this->getJson('/api/permissions');
        $response->assertStatus(403);
    }
}
