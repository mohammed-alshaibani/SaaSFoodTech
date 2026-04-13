<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $provider;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions first
        $this->seedRolesAndPermissions();
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        \Illuminate\Support\Facades\Event::fake();

        // Create test users
        $this->customer = User::factory()->create(['plan' => 'free']);
        $this->customer->assignRole('customer');

        $this->provider = User::factory()->create();
        $this->provider->assignRole('provider_admin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function seedRolesAndPermissions(): void
    {
        // Create permissions
        $permissions = [
            'request.create',
            'request.accept',
            'request.complete',
            'request.view_all',
            'user.manage',
            'permission.assign',
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::all());

        $providerAdmin = Role::firstOrCreate(['name' => 'provider_admin']);
        $providerAdmin->givePermissionTo(['request.accept', 'request.complete', 'request.view_all', 'permission.assign']);

        $providerEmployee = Role::firstOrCreate(['name' => 'provider_employee']);
        $providerEmployee->givePermissionTo(['request.accept', 'request.complete']);

        $customer = Role::firstOrCreate(['name' => 'customer']);
        $customer->givePermissionTo(['request.create']);
    }

    public function test_customer_can_create_order()
    {
        $orderData = [
            'title' => 'Test Order',
            'description' => 'This is a test order description that is at least 20 characters long.',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ];

        $response = $this->actingAs($this->customer)
            ->postJson('/api/requests', $orderData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'customer_id',
                ],
            ]);
    }

    public function test_customer_cannot_accept_own_order()
    {
        // Create an order
        $order = ServiceRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
        ]);

        // Try to accept own order
        $response = $this->actingAs($this->customer)
            ->patchJson("/api/requests/{$order->id}/accept");

        $response->assertStatus(403);
    }

    public function test_provider_cannot_accept_already_accepted_order()
    {
        // Create an order and assign to another provider
        $otherProvider = User::factory()->create();
        $otherProvider->assignRole('provider_admin');

        $order = ServiceRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $otherProvider->id,
            'status' => 'accepted',
        ]);

        // Try to accept already accepted order
        $response = $this->actingAs($this->provider)
            ->patchJson("/api/requests/{$order->id}/accept");

        $response->assertStatus(409);
    }

    public function test_provider_cannot_complete_unaccepted_order()
    {
        // Create a pending order
        $order = ServiceRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
        ]);

        // Try to complete unaccepted order
        $response = $this->actingAs($this->provider)
            ->patchJson("/api/requests/{$order->id}/complete");

        $response->assertStatus(403);
    }

    public function test_provider_can_complete_accepted_order()
    {
        // Create an order assigned to provider
        $order = ServiceRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $this->provider->id,
            'status' => 'accepted',
        ]);

        // Complete accepted order
        $response = $this->actingAs($this->provider)
            ->patchJson("/api/requests/{$order->id}/complete");

        $response->assertStatus(200);
    }

    public function test_free_user_cannot_exceed_request_limit()
    {
        // Create 3 orders (free limit)
        ServiceRequest::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
        ]);

        // Try to create 4th order
        $response = $this->actingAs($this->customer)
            ->postJson('/api/requests', [
                'title' => 'Fourth Order',
                'description' => 'This is a fourth test order description that is at least 20 characters long.',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
            ]);

        $response->assertStatus(403);
    }

    public function test_geographic_validation()
    {
        // Test invalid latitude
        $response = $this->actingAs($this->customer)
            ->postJson('/api/requests', [
                'title' => 'Test Order',
                'description' => 'This is a test order description that is at least 20 characters long.',
                'latitude' => 91.0, // Invalid latitude
                'longitude' => -74.0060,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_admin_bypasses_all_restrictions()
    {
        // Create order assigned to another provider
        $order = ServiceRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $this->provider->id,
            'status' => 'accepted',
        ]);

        // Admin can complete any order
        $response = $this->actingAs($this->admin)
            ->patchJson("/api/requests/{$order->id}/complete");

        $response->assertStatus(200);
    }

    public function test_subscription_upgrade_flow()
    {
        // Test plan upgrade
        $response = $this->actingAs($this->customer)
            ->postJson('/api/subscription/upgrade', [
                'plan' => 'premium',
                'payment_method' => 'card',
                'payment_token' => 'tok_test_token_123456789',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'new_plan',
                    'previous_plan',
                ],
            ]);

        // Check user plan was updated
        $this->assertDatabaseHas('users', [
            'id' => $this->customer->id,
            'plan' => 'premium',
        ]);
    }

    public function test_ai_categorization_endpoint()
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/ai/categorize', [
                'title' => 'Restaurant Delivery',
                'description' => 'Need food delivered from Italian restaurant downtown',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'category',
                    'price_range',
                    'confidence',
                    'ai_categorized',
                ],
            ]);
    }

    public function test_ai_pricing_suggestion_endpoint()
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/ai/suggest-pricing', [
                'title' => 'Catering for Office Party',
                'description' => 'Need catering for 20 people, Italian food preferred',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'min_price',
                    'max_price',
                    'recommended_price',
                    'reasoning',
                    'ai_priced',
                ],
            ]);
    }
}
