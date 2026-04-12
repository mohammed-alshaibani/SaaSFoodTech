<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DescriptionEnhancerService
{
    /**
     * Enhance a service request description using AI.
     */
    public function enhance(string $title, string $description): string
    {
        if (empty($description)) {
            return $description;
        }

        try {
            // Original code likely used OpenAI gpt-4o-mini as per test
            $response = Http::timeout(config('services.ai.timeout', 10))
                ->withToken(config('services.openai.key'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a professional service request enhancer.'],
                        ['role' => 'user', 'content' => "Enhance this service title: '{$title}' and description: '{$description}'"],
                    ],
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content') ?? $description;
            }

            Log::warning("AI Enhancement failed: " . $response->body());
        } catch (\Exception $e) {
            Log::error("AI Enhancement Error: " . $e->getMessage());
        }

        return $description;
    }
}
