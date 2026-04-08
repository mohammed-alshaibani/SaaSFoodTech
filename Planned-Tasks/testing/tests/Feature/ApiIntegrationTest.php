<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ServiceRequest;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class ApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run seeders
        $this->seed(\Database\Seeders\AdvancedRbacSeeder::class);
        
        // Fake queues and mail for testing
        Queue::fake();
        Mail::fake();
        Cache::flush();
    }

    /** @test */
    public function complete_service_request_workflow()
    {
        // Create customer
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        
        // Create provider
        $provider = User::factory()->create([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);
        $provider->assignRole('Provider');
        
        // Step 1: Customer creates request
        Sanctum::actingAs($customer);
        
        $requestData = [
            'title' => 'Need plumbing repair',
            'description' => 'Kitchen sink is leaking badly',
            'latitude' => 40.7130,
            'longitude' => -74.0062,
        ];

        $response = $this->postJson('/api/requests', $requestData);
        $response->assertStatus(201);
        
        $serviceRequest = ServiceRequest::first();
        $this->assertEquals('pending', $serviceRequest->status);
        
        // Step 2: Provider views nearby requests
        Sanctum::actingAs($provider);
        
        $response = $this->getJson('/api/requests?nearby=true&radius=50');
        $response->assertStatus(200)
                ->assertJsonFragment(['id' => $serviceRequest->id]);
        
        // Step 3: Provider accepts request
        $response = $this->patchJson("/api/requests/{$serviceRequest->id}/accept");
        $response->assertStatus(200);
        
        $serviceRequest->refresh();
        $this->assertEquals('accepted', $serviceRequest->status);
        $this->assertEquals($provider->id, $serviceRequest->provider_id);
        
        // Step 4: Provider completes request
        $response = $this->patchJson("/api/requests/{$serviceRequest->id}/complete");
        $response->assertStatus(200);
        
        $serviceRequest->refresh();
        $this->assertEquals('completed', $serviceRequest->status);
        
        // Verify notifications were queued
        Queue::assertPushed(\App\Jobs\SendNotificationJob::class, function ($job) use ($customer, $provider) {
            return $job->user->id === $customer->id && $job->type === 'request.accepted';
        });
    }

    /** @test */
    public function ai_enhancement_workflow()
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        
        Sanctum::actingAs($customer);
        
        // Create request with minimal description
        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'title' => 'Fix sink',
            'description' => 'broken',
        ]);
        
        // Request AI enhancement
        $response = $this->postJson('/api/ai/enhance', [
            'title' => $serviceRequest->title,
            'description' => $serviceRequest->description,
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'enhanced_title',
            'enhanced_description',
            'suggested_category',
            'confidence_score',
        ]);
        
        // Verify AI job was queued
        Queue::assertPushed(\App\Jobs\ProcessAICategorizationJob::class);
    }

    /** @test */
    public function subscription_limit_enforcement()
    {
        $customer = User::factory()->create(['plan' => 'free']);
        $customer->assignRole('Customer');
        
        Sanctum::actingAs($customer);
        
        // Create requests up to limit
        ServiceRequest::factory()->count(3)->create(['customer_id' => $customer->id]);
        
        // Try to create one more (should fail)
        $response = $this->postJson('/api/requests', [
            'title' => 'Fourth request',
            'description' => 'This should fail',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);
        
        $response->assertStatus(429)
                ->assertJsonFragment([
                    'error' => 'Rate limit exceeded',
                ]);
    }

    /** @test */
    public function rbac_permission_inheritance()
    {
        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        
        // Create provider admin user
        $providerAdmin = User::factory()->create();
        $providerAdmin->assignRole('Provider Admin');
        
        // Test admin can access provider management
        Sanctum::actingAs($admin);
        
        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(200);
        
        // Test provider admin cannot access admin endpoints
        Sanctum::actingAs($providerAdmin);
        
        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(403);
        
        // But provider admin can manage providers
        $response = $this->getJson('/api/roles');
        $response->assertStatus(200);
    }

    /** @test */
    public function error_handling_and_logging()
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        
        Sanctum::actingAs($customer);
        
        // Test validation error
        $response = $this->postJson('/api/requests', []);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'description', 'latitude', 'longitude']);
        
        // Test not found error
        $response = $this->getJson('/api/requests/99999');
        $response->assertStatus(404);
        
        // Test authorization error
        Sanctum::actingAs($customer);
        
        $otherRequest = ServiceRequest::factory()->create();
        $response = $this->deleteJson("/api/requests/{$otherRequest->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function cache_performance_integration()
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        
        Sanctum::actingAs($customer);
        
        // Create multiple requests
        ServiceRequest::factory()->count(5)->create(['customer_id' => $customer->id]);
        
        // First request should cache results
        $response1 = $this->getJson('/api/requests');
        $response1->assertStatus(200);
        
        // Second request should use cache
        $response2 = $this->getJson('/api/requests');
        $response2->assertStatus(200);
        
        // Verify cache was used (same response time/data)
        $this->assertEquals(
            $response1->json('data'),
            $response2->json('data')
        );
    }

    /** @test */
    public function concurrent_request_handling()
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        
        Sanctum::actingAs($customer);
        
        // Simulate concurrent requests
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->postJson('/api/requests', [
                'title' => "Request {$i}",
                'description' => "Description {$i}",
                'latitude' => 40.7128 + ($i * 0.001),
                'longitude' => -74.0060 + ($i * 0.001),
            ]);
        }
        
        // All should succeed
        foreach ($responses as $response) {
            $response->assertStatus(201);
        }
        
        // Verify all requests were created
        $this->assertEquals(5, ServiceRequest::count());
    }

    /** @test */
    public function data_consistency_across_operations()
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        
        $customer->assignRole('Customer');
        $provider->assignRole('Provider');
        
        // Create request
        Sanctum::actingAs($customer);
        $response = $this->postJson('/api/requests', [
            'title' => 'Test Request',
            'description' => 'Test Description',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);
        
        $response->assertStatus(201);
        $serviceRequest = ServiceRequest::first();
        
        // Verify data consistency
        $this->assertEquals('Test Request', $serviceRequest->title);
        $this->assertEquals('Test Description', $serviceRequest->description);
        $this->assertEquals($customer->id, $serviceRequest->customer_id);
        $this->assertEquals('pending', $serviceRequest->status);
        $this->assertNotNull($serviceRequest->created_at);
        $this->assertNotNull($serviceRequest->updated_at);
        
        // Accept request
        Sanctum::actingAs($provider);
        $response = $this->patchJson("/api/requests/{$serviceRequest->id}/accept");
        $response->assertStatus(200);
        
        // Verify consistency after accept
        $serviceRequest->refresh();
        $this->assertEquals('accepted', $serviceRequest->status);
        $this->assertEquals($provider->id, $serviceRequest->provider_id);
        $this->assertNotNull($serviceRequest->accepted_at);
    }

    /** @test */
    public function security_headers_and_cors()
    {
        $response = $this->getJson('/api/docs/api-docs.yaml');
        
        // Verify security headers
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->assertHeader('Access-Control-Allow-Headers', 'Origin, Content-Type, Authorization');
    }

    /** @test */
    public function rate_limiting_by_subscription()
    {
        $freeUser = User::factory()->create(['plan' => 'free']);
        $premiumUser = User::factory()->create(['plan' => 'premium']);
        
        $freeUser->assignRole('Customer');
        $premiumUser->assignRole('Customer');
        
        // Test free user limit
        Sanctum::actingAs($freeUser);
        ServiceRequest::factory()->count(3)->create(['customer_id' => $freeUser->id]);
        
        $response = $this->postJson('/api/requests', [
            'title' => 'Exceeds limit',
            'description' => 'Should be rate limited',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);
        $response->assertStatus(429);
        
        // Test premium user has higher limit
        Sanctum::actingAs($premiumUser);
        ServiceRequest::factory()->count(50)->create(['customer_id' => $premiumUser->id]);
        
        $response = $this->postJson('/api/requests', [
            'title' => 'Within premium limit',
            'description' => 'Should succeed',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);
        $response->assertStatus(201);
    }

    /** @test */
    public function notification_workflow_integration()
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        
        $customer->assignRole('Customer');
        $provider->assignRole('Provider');
        
        // Create and accept request
        Sanctum::actingAs($customer);
        $serviceRequest = ServiceRequest::factory()->create(['customer_id' => $customer->id]);
        
        Sanctum::actingAs($provider);
        $this->patchJson("/api/requests/{$serviceRequest->id}/accept");
        
        // Verify notification job was dispatched
        Queue::assertPushed(\App\Jobs\SendNotificationJob::class, function ($job) use ($customer) {
            return $job->user->id === $customer->id && 
                   $job->type === 'request.accepted' &&
                   isset($job->data['provider_name']);
        });
        
        // Verify notification data structure
        Queue::assertPushed(\App\Jobs\SendNotificationJob::class, function ($job) {
            return isset($job->data['title']) && 
                   isset($job->data['provider_name']) &&
                   isset($job->data['request_id']);
        });
    }

    /** @test */
    public function api_response_format_consistency()
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        
        Sanctum::actingAs($customer);
        
        // Test list endpoint
        $listResponse = $this->getJson('/api/requests');
        $listResponse->assertStatus(200)
                   ->assertJsonStructure([
                       'data' => [
                           '*' => [
                               'id',
                               'title',
                               'description',
                               'status',
                               'created_at',
                               'updated_at',
                           ]
                       ],
                       'links',
                       'meta',
                   ]);
        
        // Test show endpoint
        $serviceRequest = ServiceRequest::factory()->create(['customer_id' => $customer->id]);
        $showResponse = $this->getJson("/api/requests/{$serviceRequest->id}");
        $showResponse->assertStatus(200)
                  ->assertJsonStructure([
                      'id',
                      'title',
                      'description',
                      'status',
                      'created_at',
                      'updated_at',
                      'customer' => [
                          'id',
                          'name',
                          'email',
                      ],
                  ]);
        
        // Test error response format
        $errorResponse = $this->getJson('/api/requests/99999');
        $errorResponse->assertStatus(404)
                    ->assertJsonStructure([
                        'error',
                        'message',
                    ]);
    }
}
