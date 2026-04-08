<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class ServiceRequestTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    /**
     * Test customer can create a service request.
     */
    public function test_customer_can_create_service_request(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        Sanctum::actingAs($customer);

        $requestData = [
            'title' => 'Fix my plumbing',
            'description' => 'The kitchen sink is leaking and needs repair. Water is dripping from the faucet and the pressure is low.',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'category' => 'plumbing',
            'urgency' => 'high',
        ];

        $response = $this->postJson('/api/requests', $requestData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'latitude',
                    'longitude',
                    'customer',
                    'created_at',
                ],
                'request_id',
                'timestamp',
            ]);

        $this->assertDatabaseHas('service_requests', [
            'title' => $requestData['title'],
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Test provider cannot create service requests.
     */
    public function test_provider_cannot_create_service_request(): void
    {
        $provider = User::factory()->create();
        $provider->assignRole('provider_admin');
        Sanctum::actingAs($provider);

        $requestData = [
            'title' => 'Fix my plumbing',
            'description' => 'The kitchen sink is leaking.',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ];

        $response = $this->postJson('/api/requests', $requestData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                ],
            ]);
    }

    /**
     * Test customer can view their own requests.
     */
    public function test_customer_can_view_own_requests(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        Sanctum::actingAs($customer);

        $request = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $response = $this->getJson('/api/requests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'status',
                        'created_at',
                    ],
                ],
            ]);

        $this->assertEquals(1, count($response->json('data')));
    }

    /**
     * Test provider can view pending requests.
     */
    public function test_provider_can_view_pending_requests(): void
    {
        $provider = User::factory()->create();
        $provider->assignRole('provider_admin');
        Sanctum::actingAs($provider);

        // Create pending requests
        ServiceRequest::factory()->count(3)->create(['status' => 'pending']);
        // Create accepted requests (should not be visible to other providers)
        ServiceRequest::factory()->create(['status' => 'accepted']);

        $response = $this->getJson('/api/requests');

        $response->assertStatus(200);
        $this->assertEquals(3, count($response->json('data')));
    }

    /**
     * Test provider can accept a pending request.
     */
    public function test_provider_can_accept_pending_request(): void
    {
        $provider = User::factory()->create();
        $provider->assignRole('provider_admin');
        Sanctum::actingAs($provider);

        $request = ServiceRequest::factory()->create(['status' => 'pending']);

        $response = $this->patchJson("/api/requests/{$request->id}/accept", [
            'provider_notes' => 'I can handle this job.',
            'estimated_completion' => now()->addDays(2)->toDateString(),
        ]);

        $response->assertStatus(200);
        
        $request->refresh();
        $this->assertEquals('accepted', $request->status);
        $this->assertEquals($provider->id, $request->provider_id);
    }

    /**
     * Test provider cannot accept their own request.
     */
    public function test_provider_cannot_accept_own_request(): void
    {
        $user = User::factory()->create();
        $user->assignRole('provider_admin');
        Sanctum::actingAs($user);

        $request = ServiceRequest::factory()->create([
            'customer_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->patchJson("/api/requests/{$request->id}/accept");

        $response->assertStatus(403);
    }

    /**
     * Test request validation rules.
     */
    public function test_service_request_validation(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        Sanctum::actingAs($customer);

        // Test missing required fields
        $response = $this->postJson('/api/requests', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'latitude', 'longitude']);

        // Test invalid coordinates
        $response = $this->postJson('/api/requests', [
            'title' => 'Test Request',
            'description' => 'Test description with minimum length requirement.',
            'latitude' => 91.0, // Invalid latitude
            'longitude' => -74.0060,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude']);

        // Test title too short
        $response = $this->postJson('/api/requests', [
            'title' => 'Hi', // Too short
            'description' => 'Test description with minimum length requirement.',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /**
     * Test nearby requests filtering.
     */
    public function test_nearby_requests_filtering(): void
    {
        $provider = User::factory()->create();
        $provider->assignRole('provider_admin');
        Sanctum::actingAs($provider);

        // Create requests at different distances
        $nearbyRequest = ServiceRequest::factory()->create([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'status' => 'pending',
        ]);

        $farRequest = ServiceRequest::factory()->create([
            'latitude' => 51.5074, // London (far from NYC)
            'longitude' => -0.1278,
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/requests?latitude=40.7128&longitude=-74.0060&radius=50');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals($nearbyRequest->id, $response->json('data.0.id'));
    }

    /**
     * Test file upload functionality.
     */
    public function test_file_upload(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        Sanctum::actingAs($customer);

        $request = ServiceRequest::factory()->create(['customer_id' => $customer->id]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('test.jpg', 1000, 1000);

        $response = $this->postJson('/api/attachments/upload', [
            'file' => $file,
            'service_request_id' => $request->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'filename',
                    'original_filename',
                    'file_type',
                    'file_size',
                    'download_url',
                ],
            ]);

        $this->assertDatabaseHas('attachments', [
            'service_request_id' => $request->id,
            'original_filename' => 'test.jpg',
            'file_type' => 'image',
        ]);
    }

    /**
     * Test file upload validation.
     */
    public function test_file_upload_validation(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        Sanctum::actingAs($customer);

        // Test file too large
        $largeFile = \Illuminate\Http\UploadedFile::fake()->create('large.pdf', 15000); // 15MB

        $response = $this->postJson('/api/attachments/upload', [
            'file' => $largeFile,
            'service_request_id' => 999, // Non-existent request
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_request_id']);
    }
}
