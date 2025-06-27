<?php

namespace App\Services;

use App\Models\UserNotification;
use App\Models\User;
use App\Models\Environment;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a new payment notification for a user.
     *
     * @param int $environmentId
     * @param int $userId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array $data
     * @return UserNotification|null
     */
    public function createPaymentNotification(
        int $environmentId,
        int $userId,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): ?UserNotification {
        try {
            return UserNotification::create([
                'environment_id' => $environmentId,
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create payment notification', [
                'error' => $e->getMessage(),
                'environment_id' => $environmentId,
                'user_id' => $userId,
                'type' => $type
            ]);
            return null;
        }
    }

    /**
     * Create a payment success notification.
     *
     * @param int $environmentId
     * @param int $userId
     * @param string $orderId
     * @param float $amount
     * @param string $currency
     * @return UserNotification|null
     */
    public function createPaymentSuccessNotification(
        int $environmentId,
        int $userId,
        string $orderId,
        float $amount,
        string $currency = 'USD'
    ): ?UserNotification {
        $title = 'Payment Successful';
        $message = "Your payment of {$amount} {$currency} for order #{$orderId} was successful.";
        
        return $this->createPaymentNotification(
            $environmentId,
            $userId,
            'payment_success',
            $title,
            $message,
            [
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'timestamp' => now()->toIso8601String()
            ]
        );
    }

    /**
     * Create a payment failed notification.
     *
     * @param int $environmentId
     * @param int $userId
     * @param string $orderId
     * @param float $amount
     * @param string $currency
     * @param string $reason
     * @return UserNotification|null
     */
    public function createPaymentFailedNotification(
        int $environmentId,
        int $userId,
        string $orderId,
        float $amount,
        string $currency = 'USD',
        string $reason = 'Unknown error'
    ): ?UserNotification {
        $title = 'Payment Failed';
        $message = "Your payment of {$amount} {$currency} for order #{$orderId} failed. Reason: {$reason}";
        
        return $this->createPaymentNotification(
            $environmentId,
            $userId,
            'payment_failed',
            $title,
            $message,
            [
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'reason' => $reason,
                'timestamp' => now()->toIso8601String()
            ]
        );
    }

    /**
     * Create a subscription created notification.
     *
     * @param int $environmentId
     * @param int $userId
     * @param string $subscriptionId
     * @param string $productName
     * @param string $endDate
     * @return UserNotification|null
     */
    public function createSubscriptionCreatedNotification(
        int $environmentId,
        int $userId,
        string $subscriptionId,
        string $productName,
        string $endDate
    ): ?UserNotification {
        $title = 'Subscription Created';
        $message = "Your subscription to {$productName} has been created. It will expire on {$endDate}.";
        
        return $this->createPaymentNotification(
            $environmentId,
            $userId,
            'subscription_created',
            $title,
            $message,
            [
                'subscription_id' => $subscriptionId,
                'product_name' => $productName,
                'end_date' => $endDate,
                'timestamp' => now()->toIso8601String()
            ]
        );
    }

    /**
     * Create a subscription updated notification.
     *
     * @param int $environmentId
     * @param int $userId
     * @param string $subscriptionId
     * @param string $productName
     * @param string $endDate
     * @return UserNotification|null
     */
    public function createSubscriptionUpdatedNotification(
        int $environmentId,
        int $userId,
        string $subscriptionId,
        string $productName,
        string $endDate
    ): ?UserNotification {
        $title = 'Subscription Updated';
        $message = "Your subscription to {$productName} has been updated. It will now expire on {$endDate}.";
        
        return $this->createPaymentNotification(
            $environmentId,
            $userId,
            'subscription_updated',
            $title,
            $message,
            [
                'subscription_id' => $subscriptionId,
                'product_name' => $productName,
                'end_date' => $endDate,
                'timestamp' => now()->toIso8601String()
            ]
        );
    }

    /**
     * Create a subscription canceled notification.
     *
     * @param int $environmentId
     * @param int $userId
     * @param string $subscriptionId
     * @param string $productName
     * @param string $endDate
     * @return UserNotification|null
     */
    public function createSubscriptionCanceledNotification(
        int $environmentId,
        int $userId,
        string $subscriptionId,
        string $productName,
        string $endDate
    ): ?UserNotification {
        $title = 'Subscription Canceled';
        $message = "Your subscription to {$productName} has been canceled. It will remain active until {$endDate}.";
        
        return $this->createPaymentNotification(
            $environmentId,
            $userId,
            'subscription_canceled',
            $title,
            $message,
            [
                'subscription_id' => $subscriptionId,
                'product_name' => $productName,
                'end_date' => $endDate,
                'timestamp' => now()->toIso8601String()
            ]
        );
    }
}
