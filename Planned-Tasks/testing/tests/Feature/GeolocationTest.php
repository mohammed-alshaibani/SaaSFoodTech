<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ServiceRequest;
use App\Services\GeolocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GeolocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the advanced RBAC seeder
        $this->seed(\Database\Seeders\AdvancedRbacSeeder::class);
    }

    /** @test */
    public function provider_can_view_nearby_requests()
    {
        $provider = User::factory()->create();
        $provider->assignRole('Provider');
        
        Sanctum::actingAs($provider);

        // Create test service requests at different locations
        $nearbyRequest = ServiceRequest::create([
            'customer_id' => User::factory()->create()->id,
            'title' => 'Nearby Request',
            'description' => 'A nearby service request',
            'latitude' => 40.7128,  // New York
            'longitude' => -74.0060,
            'status' => 'pending',
        ]);

        $farRequest = ServiceRequest::create([
            'customer_id' => User::factory()->create()->id,
            'title' => 'Far Request',
            'description' => 'A far service request',
            'latitude' => 34.0522,  // Los Angeles
            'longitude' => -118.2437,
            'status' => 'pending',
        ]);

        // Search from New York with 50km radius
        $response = $this->getJson('/api/requests/nearby?latitude=40.7128&longitude=-74.0060&radius=50');
        
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data'); // Should only return the nearby request
        $response->assertJsonFragment(['title' => 'Nearby Request']);
    }

    /** @test */
    public function customer_cannot_view_nearby_requests()
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/requests/nearby?latitude=40.7128&longitude=-74.0060&radius=50');
        
        $response->assertStatus(403);
        $response->assertJsonFragment(['error' => 'Forbidden']);
    }

    /** @test */
    public function nearby_requests_excludes_user_own_requests()
    {
        $provider = User::factory()->create();
        $provider->assignRole('Provider');
        
        Sanctum::actingAs($provider);

        // Create a request by the provider themselves
        $ownRequest = ServiceRequest::create([
            'customer_id' => $provider->id,
            'title' => 'Own Request',
            'description' => 'My own service request',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'status' => 'pending',
        ]);

        // Create another request by someone else
        $otherRequest = ServiceRequest::create([
            'customer_id' => User::factory()->create()->id,
            'title' => 'Other Request',
            'description' => 'Someone else\'s service request',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/requests/nearby?latitude=40.7128&longitude=-74.0060&radius=50');
        
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data'); // Should only return the other request
        $response->assertJsonFragment(['title' => 'Other Request']);
        $response->assertJsonMissing(['title' => 'Own Request']);
    }

    /** @test */
    public function nearby_requests_validates_coordinates()
    {
        $provider = User::factory()->create();
        $provider->assignRole('Provider');
        
        Sanctum::actingAs($provider);

        // Test invalid latitude
        $response = $this->getJson('/api/requests/nearby?latitude=91&longitude=-74.0060&radius=50');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['latitude']);

        // Test invalid longitude
        $response = $this->getJson('/api/requests/nearby?latitude=40.7128&longitude=181&radius=50');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['longitude']);

        // Test missing required parameters
        $response = $this->getJson('/api/requests/nearby?latitude=40.7128');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['longitude']);
    }

    /** @test */
    public function nearby_requests_filters_by_status()
    {
        $provider = User::factory()->create();
        $provider->assignRole('Provider');
        
        Sanctum::actingAs($provider);

        // Create requests with different statuses
        $pendingRequest = ServiceRequest::create([
            'customer_id' => User::factory()->create()->id,
            'title' => 'Pending Request',
            'description' => 'A pending service request',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'status' => 'pending',
        ]);

        $acceptedRequest = ServiceRequest::create([
            'customer_id' => User::factory()->create()->id,
            'title' => 'Accepted Request',
            'description' => 'An accepted service request',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'status' => 'accepted',
        ]);

        // Default should show only pending
        $response = $this->getJson('/api/requests/nearby?latitude=40.7128&longitude=-74.0060&radius=50');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['title' => 'Pending Request']);
        $response->assertJsonMissing(['title' => 'Accepted Request']);

        // Explicitly filter by accepted
        $response = $this->getJson('/api/requests/nearby?latitude=40.7128&longitude=-74.0060&radius=50&status=accepted');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['title' => 'Accepted Request']);
        $response->assertJsonMissing(['title' => 'Pending Request']);
    }

    /** @test */
    public function geolocation_service_calculates_distance_correctly()
    {
        // Test distance between New York and Los Angeles
        $distance = GeolocationService::calculateDistance(40.7128, -74.0060, 34.0522, -118.2437);
        
        // Should be approximately 3935 km (with some tolerance for calculation method)
        $this->assertGreaterThan(3900, $distance);
        $this->assertLessThan(4000, $distance);
    }

    /** @test */
    public function geolocation_service_validates_coordinates()
    {
        // Valid coordinates
        $result = GeolocationService::validateCoordinates(40.7128, -74.0060);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);

        // Invalid latitude
        $result = GeolocationService::validateCoordinates(91, -74.0060);
        $this->assertFalse($result['valid']);
        $this->assertContains('Latitude must be between -90 and 90 degrees', $result['errors']);

        // Invalid longitude
        $result = GeolocationService::validateCoordinates(40.7128, 181);
        $this->assertFalse($result['valid']);
        $this->assertContains('Longitude must be between -180 and 180 degrees', $result['errors']);
    }

    /** @test */
    public function geolocation_service_converts_distances()
    {
        // Test km to miles
        $miles = GeolocationService::convertDistance(100, 'km', 'miles');
        $this->assertEqualsWithDelta(62.14, $miles, 0.1);

        // Test miles to km
        $km = GeolocationService::convertDistance(62.14, 'miles', 'km');
        $this->assertEqualsWithDelta(100, $km, 0.1);

        // Test km to meters
        $meters = GeolocationService::convertDistance(5, 'km', 'meters');
        $this->assertEquals(5000, $meters);
    }

    /** @test */
    public function geolocation_service_estimates_travel_time()
    {
        $travelTime = GeolocationService::estimateTravelTime(100, 50); // 100km at 50km/h
        
        $this->assertEquals(2, $travelTime['hours']);
        $this->assertEquals(0, $travelTime['minutes']);
        $this->assertEquals(120, $travelTime['total_minutes']);
        $this->assertEquals('2h 0m', $travelTime['formatted']);
    }

    /** @test */
    public function geolocation_service_gets_bounding_box()
    {
        $bbox = GeolocationService::getBoundingBox(40.7128, -74.0060, 50);
        
        $this->assertArrayHasKey('min_lat', $bbox);
        $this->assertArrayHasKey('max_lat', $bbox);
        $this->assertArrayHasKey('min_lon', $bbox);
        $this->assertArrayHasKey('max_lon', $bbox);
        
        $this->assertLessThan(40.7128, $bbox['min_lat']);
        $this->assertGreaterThan(40.7128, $bbox['max_lat']);
        $this->assertLessThan(-74.0060, $bbox['min_lon']);
        $this->assertGreaterThan(-74.0060, $bbox['max_lon']);
    }

    /** @test */
    public function geolocation_service_checks_point_in_polygon()
    {
        // Simple square polygon around New York
        $polygon = [
            [40.8, -74.2],  // Top-left
            [40.8, -73.8],  // Top-right
            [40.6, -73.8],  // Bottom-right
            [40.6, -74.2],  // Bottom-left
        ];

        // Point inside polygon (New York coordinates)
        $inside = GeolocationService::isPointInPolygon(40.7128, -74.0060, $polygon);
        $this->assertTrue($inside);

        // Point outside polygon (Los Angeles coordinates)
        $outside = GeolocationService::isPointInPolygon(34.0522, -118.2437, $polygon);
        $this->assertFalse($outside);
    }

    /** @test */
    public function nearby_requests_respects_radius_limit()
    {
        $provider = User::factory()->create();
        $provider->assignRole('Provider');
        
        Sanctum::actingAs($provider);

        // Create a request exactly 50km away
        $request = ServiceRequest::create([
            'customer_id' => User::factory()->create()->id,
            'title' => '50km Request',
            'description' => 'Exactly 50km away',
            'latitude' => 40.7128 + (50/111), // Approximate 50km north
            'longitude' => -74.0060,
            'status' => 'pending',
        ]);

        // Should be included with 50km radius
        $response = $this->getJson('/api/requests/nearby?latitude=40.7128&longitude=-74.0060&radius=50');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');

        // Should not be included with 25km radius
        $response = $this->getJson('/api/requests/nearby?latitude=40.7128&longitude=-74.0060&radius=25');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }
}
