<?php

namespace App\Services\Sales;

use App\Models\Order;
use App\Support\Sales\SalesFormCheckoutToken;

/**
 * Build the full continue-payment URL for an order.
 *
 * Sales-form guests are not signed in on the tenant domain, so their link
 * carries the signed checkout token (and registration id/gateway) the sales
 * form response and the abandoned-reminder mail use. Storefront orders use the
 * plain accessor.
 */
class OrderContinueUrl
{
    public function for(Order $order): string
    {
        $url = (string) ($order->continue_payment_url ?? '');

        if ($url === '' || $order->type !== Order::TYPE_SALES_FORM) {
            return $url;
        }

        $order->loadMissing('salesFormSubmission.salesForm');

        $query = [
            'checkout_token' => SalesFormCheckoutToken::issue($order),
        ];

        if ($order->sales_form_submission_id) {
            $query['registration'] = $order->sales_form_submission_id;
        }

        $gateway = $order->salesFormSubmission?->salesForm?->settings['payment_gateway_code'] ?? null;
        if (is_string($gateway) && $gateway !== '') {
            $query['payment_gateway'] = $gateway;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }
}
