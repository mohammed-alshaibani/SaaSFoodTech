<?php

namespace App\Services;

use App\Http\Requests\UpgradePlanRequest;
use App\Models\UserSubscription;
use App\Services\Payment\PaymentProcessorFactory;
use App\Services\Payment\PaymentProcessorInterface;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected PaymentProcessorFactory $factory;

    public function __construct(PaymentProcessorFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Process payment for subscription upgrade using strategy pattern.
     */
    public function processPayment(UpgradePlanRequest $request): bool
    {
        $paymentMethod = $request->payment_method ?? 'stripe';
        $processor = $this->factory->get($paymentMethod);

        if (!$processor) {
            Log::error('Payment processor not found', [
                'payment_method' => $paymentMethod,
                'available_processors' => array_keys($this->factory->getAvailable()),
            ]);
            return false;
        }

        Log::info('Processing payment', [
            'payment_method' => $paymentMethod,
            'processor' => $processor->getName(),
            'plan' => $request->plan,
            'amount' => $this->getPlanPrice($request->plan),
        ]);

        $paymentData = [
            'amount' => $this->getPlanPrice($request->plan) * 100, // Convert to cents
            'currency' => 'usd',
            'payment_method' => $paymentMethod,
            'plan' => $request->plan,
            'user_id' => $request->user()->id,
        ];

        return $processor->process($paymentData);
    }

    /**
     * Create payment intent using appropriate processor.
     */
    public function createPaymentIntent(array $data, ?string $paymentMethod = null): array
    {
        $paymentMethod = $paymentMethod ?? 'stripe';
        $processor = $this->factory->get($paymentMethod);

        if (!$processor) {
            throw new \InvalidArgumentException("Payment processor '{$paymentMethod}' not available");
        }

        return $processor->createPaymentIntent($data);
    }

    /**
     * Confirm payment using appropriate processor.
     */
    public function confirmPayment(string $paymentIntentId, ?string $paymentMethod = null): bool
    {
        $paymentMethod = $paymentMethod ?? 'stripe';
        $processor = $this->factory->get($paymentMethod);

        if (!$processor) {
            throw new \InvalidArgumentException("Payment processor '{$paymentMethod}' not available");
        }

        return $processor->confirmPayment($paymentIntentId);
    }

    /**
     * Handle payment webhook using appropriate processor.
     */
    public function handleWebhook(array $payload, ?string $paymentMethod = null): bool
    {
        // Try to determine payment method from webhook
        if (!$paymentMethod) {
            $paymentMethod = $this->detectPaymentMethodFromWebhook($payload);
        }

        $processor = $this->factory->get($paymentMethod);

        if (!$processor) {
            Log::warning('Webhook received for unavailable payment processor', [
                'detected_method' => $paymentMethod,
                'webhook_type' => $payload['type'] ?? 'unknown',
            ]);
            return false;
        }

        return $processor->handleWebhook($payload);
    }

    /**
     * Refund payment using appropriate processor.
     */
    public function refundPayment(string $transactionId, ?string $reason = null, ?string $paymentMethod = null): bool
    {
        $paymentMethod = $paymentMethod ?? 'stripe';
        $processor = $this->factory->get($paymentMethod);

        if (!$processor) {
            throw new \InvalidArgumentException("Payment processor '{$paymentMethod}' not available");
        }

        return $processor->refund($transactionId, $reason);
    }

    /**
     * Update payment method for subscription.
     */
    public function updatePaymentMethod(UserSubscription $subscription, array $paymentMethodData): bool
    {
        $paymentMethod = $paymentMethodData['processor'] ?? 'stripe';
        $processor = $this->factory->get($paymentMethod);

        if (!$processor) {
            throw new \InvalidArgumentException("Payment processor '{$paymentMethod}' not available");
        }

        return $processor->updatePaymentMethod($subscription->id, $paymentMethodData);
    }

    /**
     * Cancel subscription with payment provider.
     */
    public function cancelSubscription(UserSubscription $subscription): bool
    {
        // Try to determine processor from subscription
        $paymentMethod = $this->detectPaymentMethodFromSubscription($subscription);
        $processor = $this->factory->get($paymentMethod);

        if (!$processor) {
            Log::warning('Cannot cancel subscription - payment processor not available', [
                'subscription_id' => $subscription->id,
                'detected_method' => $paymentMethod,
            ]);
            return false;
        }

        return $processor->cancelSubscription($subscription->id);
    }

    /**
     * Get available payment processors.
     */
    public function getAvailableProcessors(): array
    {
        return $this->factory->getAvailable();
    }

    /**
     * Get processor information.
     */
    public function getProcessorInfo(): array
    {
        return $this->factory->getProcessorInfo();
    }

    /**
     * Get plan price based on plan name.
     */
    protected function getPlanPrice(string $plan): float
    {
        $prices = [
            'free' => 0,
            'basic' => 29.99,
            'premium' => 79.99,
            'enterprise' => 199.99,
        ];

        return $prices[$plan] ?? 0;
    }

    /**
     * Detect payment method from webhook payload.
     */
    protected function detectPaymentMethodFromWebhook(array $payload): string
    {
        // Stripe webhooks
        if (isset($payload['type']) && str_starts_with($payload['type'], 'payment_intent.')) {
            return 'stripe';
        }

        // PayPal webhooks
        if (isset($payload['event_type']) && str_starts_with($payload['event_type'], 'PAYMENT.')) {
            return 'paypal';
        }

        // Default to stripe
        return 'stripe';
    }

    /**
     * Detect payment method from subscription.
     */
    protected function detectPaymentMethodFromSubscription(UserSubscription $subscription): string
    {
        // Check for Stripe identifiers
        if ($subscription->stripe_subscription_id || $subscription->stripe_customer_id) {
            return 'stripe';
        }

        // Check for PayPal identifiers
        if ($subscription->paypal_subscription_id || $subscription->paypal_customer_id) {
            return 'paypal';
        }

        // Default to stripe
        return 'stripe';
    }
}
