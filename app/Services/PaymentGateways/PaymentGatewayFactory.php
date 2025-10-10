<?php

namespace App\Services\PaymentGateways;

use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Log;

class PaymentGatewayFactory
{
    /**
     * Available payment gateways
     *
     * @var array
     */
    protected static $gateways = [
        'stripe' => StripeGateway::class,
        'paypal' => PayPalGateway::class,
        'lygos' => LygosGateway::class,
        'monetbill' => MonetbillGateway::class,
        'taramoney' => TaraMoneyGateway::class
    ];
    
    /**
     * Create a payment gateway instance
     *
     * @param string $gateway
     * @param PaymentGatewaySetting $settings
     * @return PaymentGatewayInterface|null
     */
    public static function create(string $gateway, PaymentGatewaySetting $settings): ?PaymentGatewayInterface
    {
        if (!isset(self::$gateways[$gateway])) {
            Log::error("Payment gateway not supported: {$gateway}");
            return null;
        }
        
        Log::info("[Factory] Building {$gateway} gateway", [
            'gateway_code' => $gateway,
            'settings_id' => $settings->id,
            'settings_code' => $settings->code,
            'environment' => $settings->environment_id,
            'is_test_mode' => $settings->getSetting('test_mode', false),
            'settings_present' => !empty($settings->settings)
        ]);
        
        $gatewayClass = self::$gateways[$gateway];
        $gatewayInstance = new $gatewayClass();
        $gatewayInstance->initialize($settings);
        
        return $gatewayInstance;
    }
    
    /**
     * Get all available payment gateways
     *
     * @return array
     */
    public static function getAvailableGateways(): array
    {
        return array_keys(self::$gateways);
    }
    
    /**
     * Check if a gateway is supported
     *
     * @param string $gateway
     * @return bool
     */
    public static function isSupported(string $gateway): bool
    {
        return isset(self::$gateways[$gateway]);
    }
    
    /**
     * Register a new payment gateway
     *
     * @param string $code
     * @param string $class
     * @return void
     */
    public static function register(string $code, string $class): void
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Class {$class} does not exist");
        }
        
        if (!is_subclass_of($class, PaymentGatewayInterface::class)) {
            throw new \InvalidArgumentException("Class {$class} must implement PaymentGatewayInterface");
        }
        
        self::$gateways[$code] = $class;
    }
}
