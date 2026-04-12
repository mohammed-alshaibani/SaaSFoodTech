<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class AIController extends Controller
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        $this->model = config('services.gemini.model') ?: env('GEMINI_MODEL', 'gemini-1.5-flash');
    }

    /**
     * POST /api/ai/enhance
     * Enhance service request description
     */
    public function enhance(Request $request): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|min:10|max:5000',
            'type' => 'nullable|in:service_request,general',
        ]);

        try {
            if (empty($this->apiKey) || $this->apiKey === 'mock_key_for_testing') {
                return response()->json([
                    'success' => true,
                    'enhanced_description' => "✨ [AI Enhanced] " . $request->description . " — We take pride in delivering top-tier service. Please process this request with urgency.",
                    'original_description' => $request->description,
                ]);
            }
            $prompt = $this->buildEnhancementPrompt($request->description, $request->type);
            $response = $this->callGeminiAPI($prompt);

            return response()->json([
                'success' => true,
                'enhanced_description' => $response,
                'original_description' => $request->description,
            ]);
        } catch (\Exception $e) {
            Log::error('AI enhancement failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'AI enhancement service temporarily unavailable',
                'original_description' => $request->description,
            ], 503);
        }
    }

    /**
     * POST /api/ai/categorize
     * Categorize service request
     */
    public function categorize(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:10|max:5000',
        ]);

        try {
            if (empty($this->apiKey) || $this->apiKey === 'mock_key_for_testing') {
                return response()->json([
                    'success' => true,
                    'category' => 'other',
                    'confidence' => 0.9,
                    'ai_response' => 'Mock categorization due to missing AI API Key (Set GEMINI_API_KEY)',
                ]);
            }
            $prompt = $this->buildCategorizationPrompt($request->title, $request->description);
            $response = $this->callGeminiAPI($prompt);

            // Extract category from response
            $category = $this->extractCategory($response);

            return response()->json([
                'success' => true,
                'category' => $category,
                'confidence' => $this->calculateConfidence($category, $response),
                'ai_response' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('AI categorization failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'AI categorization service temporarily unavailable',
                'category' => 'other',
            ], 503);
        }
    }

    /**
     * POST /api/ai/suggest-pricing
     * Suggest pricing for service request
     */
    public function suggestPricing(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:10|max:5000',
            'category' => 'nullable|string',
            'urgency' => 'nullable|in:low,medium,high,emergency',
            'location' => 'nullable|string',
        ]);

        try {
            if (empty($this->apiKey) || $this->apiKey === 'mock_key_for_testing') {
                return response()->json([
                    'success' => true,
                    'suggested_price' => 150,
                    'price_range' => '120-180',
                    'currency' => 'USD',
                    'factors' => ['complexity', 'urgency', 'mock_fallback'],
                    'ai_response' => 'Mock pricing due to missing AI API Key (Set GEMINI_API_KEY)',
                ]);
            }
            $prompt = $this->buildPricingPrompt(
                $request->title,
                $request->description,
                $request->category,
                $request->urgency,
                $request->location
            );

            $response = $this->callGeminiAPI($prompt);
            $pricing = $this->extractPricing($response);

            return response()->json([
                'success' => true,
                'suggested_price' => $pricing['price'],
                'price_range' => $pricing['range'],
                'currency' => 'USD',
                'factors' => $pricing['factors'],
                'ai_response' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('AI pricing suggestion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'AI pricing service temporarily unavailable',
                'suggested_price' => null,
            ], 503);
        }
    }

    /**
     * Call Gemini API
     */
    private function callGeminiAPI(string $prompt): string
    {
        $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 500,
                'topP' => 0.8,
                'topK' => 40,
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Gemini API error: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Invalid Gemini API response structure');
        }

        return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Build enhancement prompt
     */
    private function buildEnhancementPrompt(string $description, ?string $type): string
    {
        $context = $type === 'service_request'
            ? 'This is for a service request marketplace. Make it clear, professional, and appealing to service providers.'
            : 'Make this text more professional and clear.';

        return "You are a professional content editor. Your task is to enhance the following description.

{$context}

Original description: {$description}

Please provide:
1. An enhanced version that is clearer, more professional, and more detailed
2. Keep the core meaning intact
3. Add relevant details that would be helpful
4. Make it between 50-200 words
5. Use professional but accessible language

Enhanced description:";
    }

    /**
     * Build categorization prompt
     */
    private function buildCategorizationPrompt(string $title, string $description): string
    {
        $categories = implode(', ', [
            'plumbing',
            'electrical',
            'hvac',
            'cleaning',
            'landscaping',
            'pest_control',
            'other'
        ]);

        return "You are a service categorization expert. Based on the title and description, categorize this service request.

Available categories: {$categories}

Title: {$title}
Description: {$description}

Please respond with ONLY the category name that best fits this request. Choose the most appropriate category from the list above.

Category:";
    }

    /**
     * Build pricing prompt
     */
    private function buildPricingPrompt(string $title, string $description, ?string $category, ?string $urgency, ?string $location): string
    {
        $urgencyText = $urgency ? "Urgency: {$urgency}" : "Urgency: normal";
        $categoryText = $category ? "Category: {$category}" : "Category: unspecified";
        $locationText = $location ? "Location: {$location}" : "Location: unspecified";

        return "You are a pricing expert for service marketplace. Suggest a fair price for this service request.

{$urgencyText}
{$categoryText}
{$locationText}

Title: {$title}
Description: {$description}

Please provide:
1. A specific suggested price in USD
2. A reasonable price range (min-max)
3. Key factors influencing the price
4. Consider standard market rates

Respond in JSON format:
{
    \"price\": 150,
    \"range\": \"120-180\",
    \"factors\": [\"complexity\", \"urgency\", \"location\"]
}";
    }

    /**
     * Extract category from AI response
     */
    private function extractCategory(string $response): string
    {
        $validCategories = [
            'plumbing',
            'electrical',
            'hvac',
            'cleaning',
            'landscaping',
            'pest_control',
            'other'
        ];

        // Clean response and find matching category
        $response = strtolower(trim($response));

        foreach ($validCategories as $category) {
            if (str_contains($response, $category)) {
                return $category;
            }
        }

        return 'other';
    }

    /**
     * Extract pricing from AI response
     */
    private function extractPricing(string $response): array
    {
        try {
            // Try to parse JSON response
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $data = json_decode($matches[0], true);

                if (isset($data['price'])) {
                    return [
                        'price' => (int) $data['price'],
                        'range' => $data['range'] ?? 'N/A',
                        'factors' => $data['factors'] ?? [],
                    ];
                }
            }

            // Fallback: extract price from text
            if (preg_match('/\$(\d+)/', $response, $matches)) {
                return [
                    'price' => (int) $matches[1],
                    'range' => 'N/A',
                    'factors' => [],
                ];
            }

            throw new \Exception('Could not extract pricing from response');

        } catch (\Exception $e) {
            return [
                'price' => null,
                'range' => 'N/A',
                'factors' => [],
            ];
        }
    }

    /**
     * Calculate confidence score
     */
    private function calculateConfidence(string $category, string $response): float
    {
        // Simple confidence calculation based on response clarity
        $response = strtolower($response);
        $category = strtolower($category);

        if (str_contains($response, $category)) {
            return 0.9;
        } elseif (str_word_count($response) < 10) {
            return 0.8;
        } else {
            return 0.7;
        }
    }
}
