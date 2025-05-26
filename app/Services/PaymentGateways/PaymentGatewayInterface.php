<?php

namespace App\Services\PaymentGateways;

use App\Models\Transaction;
use App\Models\PaymentGatewaySetting;

interface PaymentGatewayInterface
{
    /**
     * Initialize the payment gateway with environment-specific settings
     *
     * @param PaymentGatewaySetting $settings
     * @return void
     */
    public function initialize(PaymentGatewaySetting $settings): void;
    
    /**
     * Create a payment session/intent
     * 
     * This method should return a response with the appropriate type and gateway configuration:
     * - For Stripe: { type: 'client_secret', value: string, gateway_config: object }
     * - For PayPal: { type: 'checkout_url', value: string, gateway_config: object }
     * - For Lygos: { type: 'payment_url', value: string, gateway_config: object }
     *
     * @param Transaction $transaction
     * @param array $paymentData
     * @return array
     */
    public function createPayment(Transaction $transaction, array $paymentData = []): array;
    
    /**
     * Process a payment
     *
     * @param Transaction $transaction
     * @param array $paymentData
     * @return array
     */
    public function processPayment(Transaction $transaction, array $paymentData = []): array;
    
    /**
     * Verify a payment
     *
     * @param string $transactionId
     * @return array
     */
    public function verifyPayment(string $transactionId): array;
    
    /**
     * Process a refund
     *
     * @param Transaction $transaction
     * @param float|null $amount
     * @param string $reason
     * @return array
     */
    public function processRefund(Transaction $transaction, ?float $amount = null, string $reason = ''): array;
    
    /**
     * Get payment gateway configuration for the current environment
     *
     * @return array
     */
    public function getConfig(): array;
    
    /**
     * Verify webhook signature
     *
     * @param mixed $payload
     * @param string $signature
     * @param string $secret
     * @return bool
     */
    public function verifyWebhookSignature($payload, string $signature, string $secret): bool;
}
