<?php

namespace App\Services\Payment;

class MockPaymentProcessor implements PaymentProcessorInterface
{
    /**
     * Process a simulated payment transaction.
     */
    public function process(array $data): bool
    {
        // Simulate a successful payment without contacting a real bank
        return true;
    }

    /**
     * Refund a simulated transaction.
     */
    public function refund(string $transactionId, ?string $reason = null): bool
    {
        return true;
    }

    /**
     * Create a simulated payment intent.
     */
    public function createPaymentIntent(array $data): array
    {
        return [
            'client_secret' => 'mock_secret_' . uniqid(),
            'id' => 'mock_intent_' . uniqid(),
            'status' => 'requires_payment_method'
        ];
    }

    /**
     * Confirm a simulated payment.
     */
    public function confirmPayment(string $paymentIntentId): bool
    {
        return true;
    }

    /**
     * Handle simulated webhook events.
     */
    public function handleWebhook(array $payload): bool
    {
        return true;
    }

    /**
     * Update payment method for a subscription (simulated).
     */
    public function updatePaymentMethod(string $subscriptionId, array $paymentMethodData): bool
    {
        return true;
    }

    /**
     * Cancel a simulated subscription.
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        return true;
    }

    /**
     * Get processor name.
     */
    public function getName(): string
    {
        return 'mock';
    }

    /**
     * Check if processor is available.
     */
    public function isAvailable(): bool
    {
        // The mock simulator is always available!
        return true;
    }
}
