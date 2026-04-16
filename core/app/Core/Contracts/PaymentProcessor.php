<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Payment processing contract for a jurisdiction.
 *
 * Abstracts payment gateway interactions so each country pack
 * can integrate with locally available payment providers.
 * All monetary values are in minor currency units.
 */
interface PaymentProcessor
{
    /**
     * Create a payment intent or initialise a payment session.
     *
     * @param int    $amountMinor Amount to charge in minor currency units
     * @param string $currency    ISO 4217 currency code
     * @param array{
     *     user_id?: int,
     *     plan_id?: string,
     *     description?: string
     * } $metadata Additional context for the payment
     *
     * @return array{
     *     intent_id: string,
     *     client_secret: string,
     *     status: string,
     *     provider: string
     * } Payment intent details for client-side confirmation
     */
    public function createPaymentIntent(int $amountMinor, string $currency, array $metadata): array;

    /**
     * Get the payment methods available in the jurisdiction.
     *
     * @return array<int, array{
     *     code: string,
     *     name: string,
     *     type: string
     * }> Available payment methods (card, bank transfer, mobile wallet, etc.)
     */
    public function getAvailablePaymentMethods(): array;
}
