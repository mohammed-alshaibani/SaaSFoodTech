<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;

class DescriptionEnhancerService
{
    private const MODEL = 'gemini-1.5-flash';
    private const MAX_TOKENS = 500;

    /**
     * Enhance a service description using Gemini AI.
     * Falls back to the original description if the API call fails for any reason —
     * this ensures the form can always be submitted (graceful degradation).
     */
    public function enhance(string $title, string $currentDescription): string
    {
        try {
            $apiKey = config('services.gemini.api_key');
            $client = new GuzzleClient();
            
            $prompt = 'You are a professional service request writer for a marketplace platform. '
                    . 'Your job is to rewrite rough customer descriptions into clear, professional, '
                    . 'concise service requests (2-3 sentences). Output only the rewritten text.'
                    . "\n\n" . $this->buildPrompt($title, $currentDescription);

            $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/" . self::MODEL . ":generateContent?key=" . $apiKey, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => self::MAX_TOKENS,
                        'temperature' => 0.7,
                    ]
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            $enhanced = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

            // Return fallback if Gemini returns empty content
            return filled($enhanced) ? trim($enhanced) : $currentDescription;

        } catch (RequestException $e) {
            // Log real error server-side; never expose it to the API response
            Log::warning('AI description enhance failed', [
                'error' => $e->getMessage(),
                'title' => $title,
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            // Graceful degradation — return original unchanged
            return $currentDescription;
        } catch (\Throwable $e) {
            // Log any other unexpected errors
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
