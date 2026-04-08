<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OrderCategorizationService
{
    private const MODEL = 'gpt-4o-mini';
    private const MAX_TOKENS = 300;

    /**
     * Categorize a service request using AI.
     * Falls back to default categorization if AI call fails.
     */
    public function categorize(string $title, string $description): array
    {
        try {
            $result = OpenAI::chat()->create([
                'model' => self::MODEL,
                'max_tokens' => self::MAX_TOKENS,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a food delivery service categorizer. '
                            . 'Analyze the service request and categorize it into one of the following categories: '
                            . 'restaurant_delivery, grocery_delivery, meal_kit, catering, food_courier, other. '
                            . 'Also provide a suggested price range (low, medium, high) based on complexity. '
                            . 'Respond in JSON format: {"category": "category_name", "price_range": "range", "confidence": 0.95}',
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildCategorizationPrompt($title, $description),
                    ],
                ],
            ]);

            $response = $result->choices[0]->message->content ?? null;

            // Parse JSON response
            $categorization = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($categorization['category'])) {
                return [
                    'category' => $categorization['category'],
                    'price_range' => $categorization['price_range'] ?? 'medium',
                    'confidence' => $categorization['confidence'] ?? 0.8,
                    'ai_categorized' => true,
                ];
            }

            // Fallback to rule-based categorization
            return $this->ruleBasedCategorization($title, $description);

        } catch (\Throwable $e) {
            // Log error and fallback to rule-based categorization
            Log::warning('AI categorization failed', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);

            return $this->ruleBasedCategorization($title, $description);
        }
    }

    /**
     * Suggest pricing for a service request based on AI analysis.
     */
    public function suggestPricing(string $title, string $description, string $category): array
    {
        try {
            $result = OpenAI::chat()->create([
                'model' => self::MODEL,
                'max_tokens' => self::MAX_TOKENS,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a food delivery pricing expert. '
                            . 'Analyze the service request and suggest appropriate pricing. '
                            . 'Consider factors like complexity, time required, and market rates. '
                            . 'Respond in JSON format: {"min_price": 25.00, "max_price": 75.00, "recommended_price": 45.00, "reasoning": "Based on complexity and time"}',
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildPricingPrompt($title, $description, $category),
                    ],
                ],
            ]);

            $response = $result->choices[0]->message->content ?? null;
            $pricing = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($pricing['recommended_price'])) {
                return [
                    'min_price' => $pricing['min_price'] ?? 25.00,
                    'max_price' => $pricing['max_price'] ?? 100.00,
                    'recommended_price' => $pricing['recommended_price'] ?? 45.00,
                    'reasoning' => $pricing['reasoning'] ?? 'Based on complexity analysis',
                    'ai_priced' => true,
                ];
            }

            // Fallback to rule-based pricing
            return $this->ruleBasedPricing($category);

        } catch (\Throwable $e) {
            Log::warning('AI pricing suggestion failed', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);

            return $this->ruleBasedPricing($category);
        }
    }

    /**
     * Build categorization prompt.
     */
    private function buildCategorizationPrompt(string $title, string $description): string
    {
        return <<<PROMPT
        Service Title: "{$title}"
        Customer Description: "{$description}"

        Please categorize this food delivery service request and suggest a price range.
        Categories: restaurant_delivery, grocery_delivery, meal_kit, catering, food_courier, other
        Price ranges: low (under $30), medium ($30-70), high (over $70)
        PROMPT;
    }

    /**
     * Build pricing prompt.
     */
    private function buildPricingPrompt(string $title, string $description, string $category): string
    {
        return <<<PROMPT
        Service Title: "{$title}"
        Customer Description: "{$description}"
        Category: "{$category}"

        Please suggest appropriate pricing for this food delivery service.
        Consider: complexity, time required, delivery distance, market rates.
        PROMPT;
    }

    /**
     * Rule-based categorization fallback.
     */
    private function ruleBasedCategorization(string $title, string $description): array
    {
        $title = strtolower($title);
        $description = strtolower($description);

        if (str_contains($title, 'restaurant') || str_contains($description, 'restaurant')) {
            return ['category' => 'restaurant_delivery', 'price_range' => 'medium', 'confidence' => 0.7, 'ai_categorized' => false];
        }

        if (str_contains($title, 'grocery') || str_contains($description, 'grocery')) {
            return ['category' => 'grocery_delivery', 'price_range' => 'low', 'confidence' => 0.7, 'ai_categorized' => false];
        }

        if (str_contains($title, 'cater') || str_contains($description, 'cater')) {
            return ['category' => 'catering', 'price_range' => 'high', 'confidence' => 0.7, 'ai_categorized' => false];
        }

        if (str_contains($title, 'meal kit') || str_contains($description, 'meal kit')) {
            return ['category' => 'meal_kit', 'price_range' => 'medium', 'confidence' => 0.7, 'ai_categorized' => false];
        }

        return ['category' => 'other', 'price_range' => 'medium', 'confidence' => 0.5, 'ai_categorized' => false];
    }

    /**
     * Rule-based pricing fallback.
     */
    private function ruleBasedPricing(string $category): array
    {
        $pricing = [
            'restaurant_delivery' => ['min' => 25.00, 'max' => 80.00, 'recommended' => 45.00],
            'grocery_delivery' => ['min' => 15.00, 'max' => 50.00, 'recommended' => 30.00],
            'meal_kit' => ['min' => 35.00, 'max' => 120.00, 'recommended' => 65.00],
            'catering' => ['min' => 100.00, 'max' => 500.00, 'recommended' => 250.00],
            'food_courier' => ['min' => 20.00, 'max' => 60.00, 'recommended' => 35.00],
            'other' => ['min' => 25.00, 'max' => 100.00, 'recommended' => 50.00],
        ];

        $prices = $pricing[$category] ?? $pricing['other'];

        return [
            'min_price' => $prices['min'],
            'max_price' => $prices['max'],
            'recommended_price' => $prices['recommended'],
            'reasoning' => 'Based on category averages',
            'ai_priced' => false,
        ];
    }
}
