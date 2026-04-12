<?php

namespace Tests\Unit;

use App\Http\Middleware\ApiSecurityMiddleware;
use App\Http\Middleware\RateLimitByPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Cache\RateLimiter;
use Mockery;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    /**
     * Test API security middleware adds request ID.
     */
    public function test_api_security_middleware_adds_request_id(): void
    {
        $middleware = new ApiSecurityMiddleware();
        $request = Request::create('/api/test', 'POST');

        $next = function ($req) {
            return new JsonResponse(['test' => 'data']);
        };

        $response = $middleware->handle($request, $next);

        $this->assertNotNull($response->headers->get('X-Request-ID'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
    }

    /**
     * Test API security middleware validates content type.
     */
    public function test_api_security_middleware_validates_content_type(): void
    {
        $middleware = new ApiSecurityMiddleware();
        $request = Request::create('/api/test', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'text/plain'
        ]);

        $next = function ($req) {
            return new JsonResponse(['test' => 'data']);
        };

        $response = TestResponse::fromBaseResponse($middleware->handle($request, $next));

        $response->assertStatus(415)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CONTENT_TYPE',
                ],
            ]);
    }

    /**
     * Test API security middleware validates API version.
     */
    public function test_api_security_middleware_validates_api_version(): void
    {
        $middleware = new ApiSecurityMiddleware();
        $request = Request::create('/api/test', 'GET', [], [], [], [
            'HTTP_X_API_VERSION' => '2.0'
        ]);

        $next = function ($req) {
            return new JsonResponse(['test' => 'data']);
        };

        $response = TestResponse::fromBaseResponse($middleware->handle($request, $next));

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_API_VERSION',
                ],
            ]);
    }

    /**
     * Test rate limiting middleware for free plan users.
     */
    public function test_rate_limiting_free_plan(): void
    {
        $user = User::factory()->create(['plan' => 'free']);

        $rateLimiter = Mockery::mock(RateLimiter::class);
        $rateLimiter->shouldReceive('tooManyAttempts')->andReturn(false);
        $rateLimiter->shouldReceive('retriesLeft')->andReturn(95);
        $rateLimiter->shouldReceive('availableIn')->andReturn(60);

        $middleware = new RateLimitByPlan($rateLimiter);
        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $next = function ($req) {
            return new JsonResponse(['test' => 'data']);
        };

        $response = TestResponse::fromBaseResponse($middleware->handle($request, $next));

        $response->assertStatus(200);
        $this->assertEquals('100', $response->headers->get('X-RateLimit-Limit'));
    }

    /**
     * Test rate limiting middleware for paid plan users.
     */
    public function test_rate_limiting_paid_plan(): void
    {
        $user = User::factory()->create(['plan' => 'paid']);

        $rateLimiter = Mockery::mock(RateLimiter::class);
        $rateLimiter->shouldReceive('tooManyAttempts')->andReturn(false);
        $rateLimiter->shouldReceive('retriesLeft')->andReturn(950);
        $rateLimiter->shouldReceive('availableIn')->andReturn(60);

        $middleware = new RateLimitByPlan($rateLimiter);
        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $next = function ($req) {
            return new JsonResponse(['test' => 'data']);
        };

        $response = TestResponse::fromBaseResponse($middleware->handle($request, $next));

        $response->assertStatus(200);
        $this->assertEquals('1000', $response->headers->get('X-RateLimit-Limit'));
    }

    /**
     * Test rate limiting middleware when limit exceeded.
     */
    public function test_rate_limiting_exceeded(): void
    {
        $user = User::factory()->create(['plan' => 'free']);

        $rateLimiter = Mockery::mock(RateLimiter::class);
        $rateLimiter->shouldReceive('tooManyAttempts')->andReturn(true);
        $rateLimiter->shouldReceive('availableIn')->andReturn(120);

        $middleware = new RateLimitByPlan($rateLimiter);
        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $next = function ($req) {
            return new JsonResponse(['test' => 'data']);
        };

        $response = TestResponse::fromBaseResponse($middleware->handle($request, $next));

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                ],
            ]);

        $this->assertEquals('120', $response->headers->get('Retry-After'));
        $this->assertEquals('0', $response->headers->get('X-RateLimit-Remaining'));
    }

    /**
     * Test rate limiting middleware for anonymous users.
     */
    public function test_rate_limiting_anonymous_user(): void
    {
        $rateLimiter = Mockery::mock(RateLimiter::class);
        $rateLimiter->shouldReceive('tooManyAttempts')->andReturn(false);
        $rateLimiter->shouldReceive('retriesLeft')->andReturn(55);
        $rateLimiter->shouldReceive('availableIn')->andReturn(60);

        $middleware = new RateLimitByPlan($rateLimiter);
        $request = Request::create('/api/test', 'GET');

        $next = function ($req) {
            return new JsonResponse(['test' => 'data']);
        };

        $response = TestResponse::fromBaseResponse($middleware->handle($request, $next));

        $response->assertStatus(200);
        $this->assertEquals('60', $response->headers->get('X-RateLimit-Limit'));
    }

    /**
     * Test rate limiting middleware for AI endpoint.
     */
    public function test_rate_limiting_ai_endpoint(): void
    {
        $user = User::factory()->create(['plan' => 'free']);

        $rateLimiter = Mockery::mock(RateLimiter::class);
        $rateLimiter->shouldReceive('tooManyAttempts')->andReturn(false);
        $rateLimiter->shouldReceive('retriesLeft')->andReturn(4);
        $rateLimiter->shouldReceive('availableIn')->andReturn(60);

        $middleware = new RateLimitByPlan($rateLimiter);
        $request = Request::create('/api/ai/enhance', 'POST');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $next = function ($req) {
            return new JsonResponse(['test' => 'data']);
        };

        $response = TestResponse::fromBaseResponse($middleware->handle($request, $next));

        $response->assertStatus(200);
        $this->assertEquals('5', $response->headers->get('X-RateLimit-Limit'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
