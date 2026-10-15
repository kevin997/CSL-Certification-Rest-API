<?php

namespace App\Support\Sales;

use App\Models\Order;
use Illuminate\Support\Facades\Crypt;
use Throwable;

final class SalesFormCheckoutToken
{
    public static function issue(Order $order): string
    {
        return Crypt::encryptString(json_encode([
            'order_id' => $order->id,
            'environment_id' => $order->environment_id,
            'submission_id' => $order->sales_form_submission_id,
            'expires_at' => now()->addDay()->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    public static function valid(?string $token, Order $order): bool
    {
        if (! $token || $order->type !== Order::TYPE_SALES_FORM || ! $order->sales_form_submission_id) {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        return is_array($payload)
            && ($payload['order_id'] ?? null) === $order->id
            && ($payload['environment_id'] ?? null) === $order->environment_id
            && ($payload['submission_id'] ?? null) === $order->sales_form_submission_id
            && is_int($payload['expires_at'] ?? null)
            && $payload['expires_at'] >= now()->timestamp;
    }
}
