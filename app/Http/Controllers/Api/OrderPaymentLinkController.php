<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Services\Sales\ShortLinkService;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderPaymentLinkController extends Controller
{
    public function __invoke(Request $request, int $orderId, ShortLinkService $links): JsonResponse
    {
        // Use only the host or verified token binding, not environment_id from
        // query/body. A payment link is not ordinary order metadata: a sales
        // form link grants the recipient access to that one guest checkout.
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);
        $environment = $context instanceof EnvironmentContext ? $context->environment : null;
        abort_unless($environment && $request->user()?->isStaffIn($environment->id), 403);

        $order = Order::query()->where('environment_id', $environment->id)->findOrFail($orderId);
        abort_unless(
            $order->status === Order::STATUS_PENDING
                && $order->total_amount > 0
                && in_array($order->type, [Order::TYPE_STOREFRONT, Order::TYPE_SUBSCRIPTION_PRODUCT, Order::TYPE_SALES_FORM], true),
            409,
            'This order cannot continue payment.'
        );

        $order->setRelation('environment', $environment);
        if ($order->type === Order::TYPE_SALES_FORM) {
            $order->load('salesFormSubmission.salesForm');
            $submission = $order->salesFormSubmission;
            abort_unless(
                $submission
                    && (int) $submission->environment_id === (int) $environment->id
                    && (int) $submission->user_id === (int) $order->user_id
                    && $submission->status !== SalesFormSubmission::STATUS_CANCELLED
                    && $submission->salesForm
                    && (int) $submission->salesForm->environment_id === (int) $environment->id,
                409,
                'This order cannot continue payment.'
            );
        }

        return response()->json(['data' => ['url' => $links->forOrder($order)]])
            ->header('Cache-Control', 'no-store, private');
    }
}
