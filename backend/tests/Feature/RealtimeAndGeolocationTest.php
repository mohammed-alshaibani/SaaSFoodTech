<?php

namespace Tests\Feature;

use App\Events\ServiceRequestUpdated;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests covering the three new modules:
 *  1. Real-time Events (ServiceRequestUpdated broadcast)
 *  2. Duplicate acceptance guard (409 on race condition)
 *  3. MySQL-native nearby scope (coordinate validation)
 */
class RealtimeAndGeolocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // Mock all events to avoid "Connection Refused" when broadcasting to Reverb
        \Illuminate\Support\Facades\Event::fake();
    }

    // ══════════════════════════════════════════════════════════════
    // Module 1 — Real-time Events
    // ══════════════════════════════════════════════════════════════

    /**
     * ServiceRequestUpdated event must be fired when a request is accepted.
     */
    public function test_service_request_updated_event_fired_on_accept(): void
    {
        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $provider->assignRole('provider_admin');
        $customer->assignRole('customer');

        $req = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($provider);

        $this->patchJson("/api/requests/{$req->id}/accept")->assertSuccessful();

        Event::assertDispatched(ServiceRequestUpdated::class, function ($event) use ($req) {
            return $event->serviceRequest->id === $req->id
                && $event->action === 'accepted';
        });
    }

    /**
     * ServiceRequestUpdated event must be fired when a request is completed.
     */
    public function test_service_request_updated_event_fired_on_complete(): void
    {
        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $provider->assignRole('provider_admin');
        $customer->assignRole('customer');

        $req = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($provider);

        $this->patchJson("/api/requests/{$req->id}/complete")->assertSuccessful();

        Event::assertDispatched(ServiceRequestUpdated::class, function ($event) use ($req) {
            return $event->serviceRequest->id === $req->id
                && $event->action === 'completed';
        });
    }

    // ══════════════════════════════════════════════════════════════
    // Module 2 — Duplicate acceptance guard
    // ══════════════════════════════════════════════════════════════

    /**
     * Two providers trying to accept the same pending request simultaneously:
     * the second attempt should receive a 409 Conflict.
     */
    public function test_duplicate_acceptance_returns_409(): void
    {
        $provider1 = User::factory()->create();
        $provider2 = User::factory()->create();
        $provider1->assignRole('provider_admin');
        $provider2->assignRole('provider_admin');

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $req = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        // Provider 1 accepts first
        Sanctum::actingAs($provider1);
        $this->patchJson("/api/requests/{$req->id}/accept")->assertSuccessful();

        // Provider 2 tries to accept the same (now accepted) request → 409
        Sanctum::actingAs($provider2);
        $this->patchJson("/api/requests/{$req->id}/accept")->assertStatus(409);

        $req->refresh();
        $this->assertEquals('accepted', $req->status);
        $this->assertEquals($provider1->id, $req->provider_id);
    }

    // ══════════════════════════════════════════════════════════════
    // Module 3 — Geolocation coordinate validation
    // ══════════════════════════════════════════════════════════════

    /**
     * lat=91 (out of range) must be rejected with 422.
     */
    public function test_invalid_latitude_rejected(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        Sanctum::actingAs($customer);

        $this->postJson('/api/requests', [
            'title' => 'Valid title here',
            'description' => 'Long enough description for the validation to pass easily.',
            'latitude' => 91.0,   // INVALID
            'longitude' => 45.0,
        ])->assertStatus(422)->assertJsonValidationErrors(['latitude']);
    }

    /**
     * lng=181 (out of range) must be rejected with 422.
     */
    public function test_invalid_longitude_rejected(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        Sanctum::actingAs($customer);

        $this->postJson('/api/requests', [
            'title' => 'Valid title here',
            'description' => 'Long enough description for the validation to pass easily.',
            'latitude' => 24.0,
            'longitude' => 181.0,  // INVALID
        ])->assertStatus(422)->assertJsonValidationErrors(['longitude']);
    }

    /**
     * Valid coordinates on the /requests/nearby endpoint must succeed (200).
     * We don't assert distance values because the test DB may be SQLite-based
     * and the scope is MySQL-only; we just confirm the endpoint accepts valid input.
     */
    public function test_nearby_endpoint_accepts_valid_coordinates(): void
    {
        $provider = User::factory()->create();
        $provider->assignRole('provider_admin');
        Sanctum::actingAs($provider);

        $this->getJson('/api/requests/nearby?latitude=24.7136&longitude=46.6753&radius=50')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    /**
     * /requests/nearby requires valid coordinate ranges.
     */
    public function test_nearby_endpoint_rejects_invalid_coordinates(): void
    {
        $provider = User::factory()->create();
        $provider->assignRole('provider_admin');
        Sanctum::actingAs($provider);

        $this->getJson('/api/requests/nearby?latitude=999&longitude=46.6753')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude']);
    }
}
