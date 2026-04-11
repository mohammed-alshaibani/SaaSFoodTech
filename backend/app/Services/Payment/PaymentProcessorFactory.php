<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

class PaymentProcessorFactory
{
    protected static array $processors = [];

    /**
     * Register a payment processor.
     */
    public static function register(string $name, PaymentProcessorInterface $processor): void
    {
        self::$processors[$name] = $processor;
    }

    /**
     * Get a payment processor by name.
     */
    public static function get(string $name): ?PaymentProcessorInterface
    {
        if (isset(self::$processors[$name])) {
            return self::$processors[$name];
        }

        // Try to resolve from container
        $processorClass = self::getProcessorClass($name);
        if ($processorClass && class_exists($processorClass)) {
            $processor = App::make($processorClass);

            if ($processor instanceof PaymentProcessorInterface && $processor->isAvailable()) {
                self::$processors[$name] = $processor;
                return $processor;
            }
        }

        Log::warning("Payment processor '{$name}' not found or not available", [
            'name' => $name,
            'registered_processors' => array_keys(self::$processors),
        ]);

        return null;
    }

    /**
     * Get the default payment processor.
     */
    public static function getDefault(): ?PaymentProcessorInterface
    {
        // Changed default fallback to 'mock'
        $default = config('payment.default_processor', 'mock');
        return self::get($default);
    }

    /**
     * Get all available processors.
     */
    public static function getAvailable(): array
    {
        $available = [];

        foreach (self::getRegisteredProcessors() as $name => $class) {
            $processor = self::get($name);
            if ($processor && $processor->isAvailable()) {
                $available[$name] = $processor;
            }
        }

        return $available;
    }

    /**
     * Get processor class by name.
     */
    protected static function getProcessorClass(string $name): ?string
    {
        $processors = self::getRegisteredProcessors();
        return $processors[$name] ?? null;
    }

    /**
     * Get registered processor classes.
     */
    protected static function getRegisteredProcessors(): array
    {
        return [
            'mock' => MockPaymentProcessor::class,
            'credit_card' => MockPaymentProcessor::class,
            'card' => MockPaymentProcessor::class,
            'paypal' => MockPaymentProcessor::class,
            'bank_transfer' => MockPaymentProcessor::class,
        ];
    }

    /**
     * Initialize default processors.
     */
    public static function initialize(): void
    {
        // Auto-register available processors
        foreach (self::getRegisteredProcessors() as $name => $class) {
            try {
                $processor = App::make($class);
                if ($processor instanceof PaymentProcessorInterface && $processor->isAvailable()) {
                    self::$processors[$name] = $processor;
                }
            } catch (\Exception $e) {
                Log::warning("Failed to initialize payment processor '{$name}'", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Clear all registered processors.
     */
    public static function clear(): void
    {
        self::$processors = [];
    }

    /**
     * Check if a processor is available.
     */
    public static function isAvailable(string $name): bool
    {
        $processor = self::get($name);
        return $processor && $processor->isAvailable();
    }

    /**
     * Get processor information.
     */
    public static function getProcessorInfo(): array
    {
        $info = [];

        foreach (self::getAvailable() as $name => $processor) {
            $info[$name] = [
                'name' => $processor->getName(),
                'available' => $processor->isAvailable(),
                'class' => get_class($processor),
            ];
        }

        return $info;
    }
}
