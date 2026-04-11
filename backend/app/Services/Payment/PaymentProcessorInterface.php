<?php

namespace App\Services\Payment;

interface PaymentProcessorInterface
{
    /**
     * Process a payment transaction.
     */
    public function process(array $data): bool;

    /**
     * Create a checkout session (redirect URL).
     */
    public function createCheckoutSession(array $data): array;

    /**
     * Refund a payment transaction.
     */
    public function refund(string $transactionId, ?string $reason = null): bool;

    /**
     * Create a payment intent for client-side processing.
     */
    public function createPaymentIntent(array $data): array;

    /**
     * Confirm a payment intent.
     */
    public function confirmPayment(string $paymentIntentId): bool;

    /**
     * Handle webhook events from the payment provider.
     */
    public function handleWebhook(array $payload): bool;

    /**
     * Update payment method for a subscription.
     */
    public function updatePaymentMethod(string $subscriptionId, array $paymentMethodData): bool;

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(string $subscriptionId): bool;

    /**
     * Get processor name.
     */
    public function getName(): string;

    /**
     * Check if processor is available.
     */
    public function isAvailable(): bool;
}
