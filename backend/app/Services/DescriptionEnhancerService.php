<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class DescriptionEnhancerService
{
    private const MODEL = 'gpt-4o-mini';
    private const MAX_TOKENS = 500;

    /**
     * Enhance a service description using OpenAI.
     * Falls back to the original description if the API call fails for any reason —
     * this ensures the form can always be submitted (graceful degradation).
     */
    public function enhance(string $title, string $currentDescription): string
    {
        try {
            $result = OpenAI::chat()->create([
                'model' => self::MODEL,
                'max_tokens' => self::MAX_TOKENS,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional service request writer for a marketplace platform. '
                            . 'Your job is to rewrite rough customer descriptions into clear, professional, '
                            . 'concise service requests (2-3 sentences). Output only the rewritten text.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildPrompt($title, $currentDescription),
                    ],
                ],
            ]);

            $enhanced = $result->choices[0]->message->content ?? null;

            // Return fallback if OpenAI returns empty content
            return filled($enhanced) ? trim($enhanced) : $currentDescription;

        } catch (\Throwable $e) {
            // Log the real error server-side; never expose it to the API response
            Log::warning('AI description enhance failed', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);

            // Graceful degradation — return original unchanged
            return $currentDescription;
        }
    }

    /**
     * Build the user-facing prompt.
     */
    private function buildPrompt(string $title, string $description): string
    {
        return <<<PROMPT
        Service title: "{$title}"
        Customer's rough description: "{$description}"

        Please rewrite the description to be professional, specific, and helpful to the service provider.
        Keep it under 3 sentences.
        PROMPT;
    }
}
