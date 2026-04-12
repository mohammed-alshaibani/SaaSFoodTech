<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    /**
     * Test user registration with valid data.
     */
    public function test_user_registration_success(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
            'phone' => '+1234567890',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'plan',
                    'roles',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'plan' => 'free',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue($user->hasRole('customer'));
    }

    /**
     * Test user registration with invalid data.
     */
    public function test_user_registration_validation(): void
    {
        // Test missing required fields
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);

        // Test weak password
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'role' => 'customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Test invalid email
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Test password mismatch
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Different123!',
            'role' => 'customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test user registration with provider role.
     */
    public function test_provider_registration_success(): void
    {
        $userData = [
            'name' => 'Jane Provider',
            'email' => 'jane@provider.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'provider',
            'company_name' => 'Jane Plumbing Services',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201);

        $user = User::where('email', 'jane@provider.com')->first();
        $this->assertTrue($user->hasRole('provider_admin'));
    }

    /**
     * Test user login with valid credentials.
     */
    public function test_user_login_success(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123!'),
        ]);
        $user->assignRole('customer');

        $loginData = [
            'email' => $user->email,
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/login', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'plan',
                    'roles',
                ],
            ]);

        $this->assertNotNull($response->json('access_token'));
    }

    /**
     * Test user login with invalid credentials.
     */
    public function test_user_login_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test user login with non-existent email.
     */
    public function test_user_login_nonexistent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test authenticated user can view profile.
     */
    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'plan',
                    'roles',
                    'permissions',
                ],
            ]);

        $this->assertEquals($user->id, $response->json('data.id'));
    }

    /**
     * Test unauthenticated user cannot view profile.
     */
    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated. Please provide a valid authentication token.',
                'error' => 'Unauthorized',
            ]);
    }

    /**
     * Test user logout.
     */
    public function test_user_logout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully.',
            ]);

        // Test token is revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    /**
     * Test login validation rules.
     */
    public function test_login_validation(): void
    {
        // Test missing email
        $response = $this->postJson('/api/login', [
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Test missing password
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Test invalid email format
        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test rate limiting on login attempts.
     */
    public function test_login_rate_limiting(): void
    {
        $user = User::factory()->create();

        // Make multiple failed login attempts
        for ($i = 0; $i < 20; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrongpassword',
            ]);
        }

        // Should be rate limited after many attempts
        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'TOO_MANY_REQUESTS',
                ],
            ]);
    }
}
