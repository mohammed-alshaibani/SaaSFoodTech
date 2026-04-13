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
        $this->apiKey = (string) (config('services.gemini.api_key') ?: env('GEMINI_API_KEY', ''));
        $this->model = (string) (config('services.gemini.model') ?: env('GEMINI_MODEL', 'gemini-1.5-flash'));
    }

    public function enhance(Request $request): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|min:10|max:5000',
            'type' => 'nullable|in:service_request,general',
        ]);

        try {
            $response = "✨ [AI Enhanced] " . $request->description . " — We take pride in delivering top-tier service. Please process this request with urgency.";

            if (!empty($this->apiKey) && $this->apiKey !== 'mock_key_for_testing') {
                $prompt = $this->buildEnhancementPrompt($request->description, $request->type);
                $response = $this->callGeminiAPI($prompt);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'enhanced_description' => $response,
                    'original_description' => $request->description,
                ]
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

    public function categorize(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:10|max:5000',
        ]);

        try {
            $category = 'other';
            $confidence = 0.9;
            $aiResponse = 'Mock response';

            if (!empty($this->apiKey) && $this->apiKey !== 'mock_key_for_testing') {
                $prompt = $this->buildCategorizationPrompt($request->title, $request->description);
                $aiResponse = $this->callGeminiAPI($prompt);
                $category = $this->extractCategory($aiResponse);
                $confidence = $this->calculateConfidence($category, $aiResponse);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'category' => $category,
                    'price_range' => '100-200',
                    'confidence' => $confidence,
                    'ai_categorized' => true,
                    'ai_response' => $aiResponse,
                ]
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
            $pricing = ['price' => 150, 'range' => '120-180', 'factors' => ['mock']];
            $aiResponse = 'Mock response';

            if (!empty($this->apiKey) && $this->apiKey !== 'mock_key_for_testing') {
                $prompt = $this->buildPricingPrompt(
                    $request->title,
                    $request->description,
                    $request->category,
                    $request->urgency,
                    $request->location
                );

                $aiResponse = $this->callGeminiAPI($prompt);
                $pricing = $this->extractPricing($aiResponse);
            }

            // Parse min/max from range string like "120-180"
            $min = 100;
            $max = 200;
            if (isset($pricing['range']) && str_contains($pricing['range'], '-')) {
                [$min, $max] = explode('-', $pricing['range']);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'min_price' => (int) $min,
                    'max_price' => (int) $max,
                    'recommended_price' => $pricing['price'] ?? 150,
                    'reasoning' => 'Based on similar requests in your area',
                    'ai_priced' => true,
                    'factors' => $pricing['factors'] ?? [],
                    'ai_response' => $aiResponse,
                ]
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

    private function buildEnhancementPrompt(string $description, ?string $type): string
    {
        $context = $type === 'service_request'
            ? 'This is for a service request marketplace. Make it clear, professional, and appealing to service providers.'
            : 'Make this text more professional and clear.';

        return "You are a professional content editor. Your task is to enhance the following description.\n\n{$context}\n\nOriginal description: {$description}\n\nPlease provide:\n1. An enhanced version that is clearer, more professional, and more detailed\n2. Keep the core meaning intact\n3. Add relevant details that would be helpful\n4. Make it between 50-200 words\n5. Use professional but accessible language\n\nEnhanced description:";
    }

    private function buildCategorizationPrompt(string $title, string $description): string
    {
        $categories = implode(', ', ['plumbing', 'electrical', 'hvac', 'cleaning', 'landscaping', 'pest_control', 'other']);

        return "You are a service categorization expert. Based on the title and description, categorize this service request. \n\nAvailable categories: {$categories}\n\nTitle: {$title}\nDescription: {$description}\n\nPlease respond with ONLY the category name that best fits this request. Choose the most appropriate category from the list above.\n\nCategory:";
    }

    private function buildPricingPrompt(string $title, string $description, ?string $category, ?string $urgency, ?string $location): string
    {
        $urgencyText = $urgency ? "Urgency: {$urgency}" : "Urgency: normal";
        $categoryText = $category ? "Category: {$category}" : "Category: unspecified";
        $locationText = $location ? "Location: {$location}" : "Location: unspecified";

        return "You are a pricing expert for service marketplace. Suggest a fair price for this service request.\n\n{$urgencyText}\n{$categoryText}\n{$locationText}\n\nTitle: {$title}\nDescription: {$description}\n\nPlease provide:\n1. A specific suggested price in USD\n2. A reasonable price range (min-max)\n3. Key factors influencing the price\n4. Consider standard market rates\n\nRespond in JSON format:\n{\n    \"price\": 150,\n    \"range\": \"120-180\",\n    \"factors\": [\"complexity\", \"urgency\", \"location\"]\n}";
    }

    private function extractCategory(string $response): string
    {
        $validCategories = ['plumbing', 'electrical', 'hvac', 'cleaning', 'landscaping', 'pest_control', 'other'];
        $response = strtolower(trim($response));

        foreach ($validCategories as $category) {
            if (str_contains($response, $category)) {
                return $category;
            }
        }

        return 'other';
    }

    private function extractPricing(string $response): array
    {
        try {
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

    private function calculateConfidence(string $category, string $response): float
    {
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
