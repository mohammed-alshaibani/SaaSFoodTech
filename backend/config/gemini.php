<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gemini API Key
    |--------------------------------------------------------------------------
    |
    | Here you may specify your Gemini API key. This will be used to authenticate
    | with Google's Generative AI API - you can find your API key in the
    | Google Cloud Console under AI & ML > Generative AI Studio.
    */

    'api_key' => env('GEMINI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Gemini Model
    |--------------------------------------------------------------------------
    |
    | The default Gemini model to use for AI operations.
    | Available models: gemini-1.5-flash, gemini-1.5-pro, gemini-1.0-pro
    */

    'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout may be used to specify the maximum number of seconds to wait
    | for a response. By default, the client will time out after 30 seconds.
    */

    'timeout' => env('GEMINI_REQUEST_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Max Tokens
    |--------------------------------------------------------------------------
    |
    | The maximum number of tokens to generate in the response.
    | This helps control costs and response length.
    */

    'max_tokens' => env('GEMINI_MAX_TOKENS', 500),

    /*
    |--------------------------------------------------------------------------
    | Temperature
    |--------------------------------------------------------------------------
    |
    | Controls randomness in the response. Lower values are more deterministic,
    | higher values are more creative. Range: 0.0 to 1.0
    */

    'temperature' => env('GEMINI_TEMPERATURE', 0.7),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Here you may specify your custom Gemini API base URL if needed.
    | This is useful for using alternative endpoints or proxies.
    */

    'base_url' => env('GEMINI_BASE_URL'),
];
