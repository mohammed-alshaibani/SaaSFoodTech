<?php

namespace Tests\Unit;

use App\Services\DescriptionEnhancerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Request;

class DescriptionEnhancerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DescriptionEnhancerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DescriptionEnhancerService::class);
    }

    /**
     * Test successful AI enhancement.
     */
    public function test_successful_ai_enhancement(): void
    {
        // Mock successful OpenAI API response
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Enhanced description with better details and professional language.'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $title = 'Fix my plumbing';
        $description = 'My sink is broken.';

        $result = $this->service->enhance($title, $description);

        $this->assertEquals('Enhanced description with better details and professional language.', $result);
        $this->assertNotEquals($description, $result);
    }

    /**
     * Test AI enhancement with API failure.
     */
    public function test_ai_enhancement_api_failure(): void
    {
        // Mock failed OpenAI API response
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'API Error'], 500)
        ]);

        $title = 'Fix my plumbing';
        $description = 'My sink is broken.';

        $result = $this->service->enhance($title, $description);

        // Should return original description on failure
        $this->assertEquals($description, $result);
    }

    /**
     * Test AI enhancement with timeout.
     */
    public function test_ai_enhancement_timeout(): void
    {
        // Mock timeout response
        Http::fake([
            'api.openai.com/*' => Http::timeout()
        ]);

        $title = 'Fix my plumbing';
        $description = 'My sink is broken.';

        $result = $this->service->enhance($title, $description);

        // Should return original description on timeout
        $this->assertEquals($description, $result);
    }

    /**
     * Test AI enhancement request format.
     */
    public function test_ai_enhancement_request_format(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Enhanced description'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $title = 'Fix my plumbing';
        $description = 'My sink is broken.';

        $this->service->enhance($title, $description);

        Http::assertSent(function (Request $request) use ($title, $description) {
            $data = $request->data();
            
            // Check if the request contains the expected structure
            return isset($data['model']) &&
                   isset($data['messages']) &&
                   $data['model'] === 'gpt-4o-mini' &&
                   str_contains($data['messages'][1]['content'], $title) &&
                   str_contains($data['messages'][1]['content'], $description);
        });
    }

    /**
     * Test AI enhancement with empty description.
     */
    public function test_ai_enhancement_empty_description(): void
    {
        $title = 'Fix my plumbing';
        $description = '';

        $result = $this->service->enhance($title, $description);

        // Should return original description if empty
        $this->assertEquals($description, $result);
    }

    /**
     * Test AI enhancement with very long description.
     */
    public function test_ai_enhancement_long_description(): void
    {
        // Mock successful response
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Enhanced long description'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $title = 'Fix my plumbing';
        $description = str_repeat('This is a very long description. ', 1000); // Very long text

        $result = $this->service->enhance($title, $description);

        $this->assertNotEquals($description, $result);
        $this->assertEquals('Enhanced long description', $result);
    }
}
