<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Processor
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment processor that will be used
    | when processing payments. You can override this per request if needed.
    |
    */
    'default_processor' => env('PAYMENT_DEFAULT_PROCESSOR', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Payment Processors Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the available payment processors and their settings.
    |
    */
    'processors' => [
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', false),
            'test_mode' => env('STRIPE_TEST_MODE', true),
            'webhook_tolerance' => 300, // seconds
        ],

        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', false),
            'sandbox' => env('PAYPAL_SANDBOX', true),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Settings
    |--------------------------------------------------------------------------
    |
    | General payment settings that apply to all processors.
    |
    */
    'settings' => [
        'currency' => env('PAYMENT_CURRENCY', 'USD'),
        'auto_refund_days' => env('PAYMENT_AUTO_REFUND_DAYS', 14),
        'retry_attempts' => env('PAYMENT_RETRY_ATTEMPTS', 3),
        'webhook_timeout' => env('PAYMENT_WEBHOOK_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhook handling for payment processors.
    |
    */
    'webhooks' => [
        'stripe' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
            'enabled_events' => [
                'payment_intent.succeeded',
                'payment_intent.payment_failed',
                'invoice.payment_succeeded',
                'invoice.payment_failed',
                'customer.subscription.deleted',
            ],
        ],

        'paypal' => [
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
            'enabled_events' => [
                'PAYMENT.CAPTURE.COMPLETED',
                'PAYMENT.CAPTURE.DENIED',
                'BILLING.SUBSCRIPTION.ACTIVATED',
                'BILLING.SUBSCRIPTION.CANCELLED',
            ],
        ],
    ],
];
