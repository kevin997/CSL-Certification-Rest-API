<?php

namespace App\Services;

use App\Models\Environment;
use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Models\Transaction;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaConversionsService
{
    public function purchase(Order $order): void
    {
        $paid = $order->status === Order::STATUS_COMPLETED
            || ($order->exists && $order->transactions()
                ->where('status', Transaction::STATUS_COMPLETED)
                ->where('total_amount', '>', 0)
                ->exists());

        if (! $paid || $order->total_amount <= 0) {
            return;
        }

        $this->send(
            'Purchase',
            'purchase:order:'.$order->id,
            array_merge([
                'value' => (float) $order->total_amount,
                'currency' => $order->currency,
            ], $this->orderContent($order)),
            $this->orderUserData($order),
            $order->environment
                ? TenantUrl::to($order->environment, '/checkout/'.$order->environment_id.'/success', ['order' => $order->id])
                : null
        );
    }

    public function initiateCheckout(Order $order): void
    {
        if ($order->status !== Order::STATUS_PENDING || $order->total_amount <= 0) {
            return;
        }

        $this->send(
            'InitiateCheckout',
            'InitiateCheckout:order:'.$order->id,
            array_merge([
                'value' => (float) $order->total_amount,
                'currency' => $order->currency,
            ], $this->orderContent($order)),
            $this->orderUserData($order),
            $order->environment ? TenantUrl::to($order->environment, '/checkout/continue-payment/'.$order->id) : null
        );
    }

    public function registration(SalesFormSubmission $submission): void
    {
        if ($submission->exists) {
            $submission->loadMissing(['orders.items.product', 'salesForm']);
        }

        $orders = $submission->relationLoaded('orders')
            ? $submission->orders
            : collect();
        $order = $orders->firstWhere('status', Order::STATUS_PENDING)
            ?? $orders->first();

        $content = $order ? $this->orderContent($order) : [];
        if ($orders->isNotEmpty()) {
            $content = array_merge([
                'value' => (float) $orders->sum('total_amount'),
                'currency' => $orders->first()?->currency,
            ], $content);
        }
        $environment = $submission->environment_id
            ? Environment::find($submission->environment_id)
            : null;

        $this->send(
            'CompleteRegistration',
            'registration:submission:'.$submission->id,
            $content,
            [
                'em' => $this->hash($submission->email),
                'ph' => $this->hashPhone($submission->phone),
                'external_id' => $this->hash((string) $submission->user_id),
            ],
            $environment && $submission->salesForm
                ? TenantUrl::to($environment, '/forms/'.$submission->salesForm->slug)
                : null
        );
    }

    /**
     * @return array{content_name?: string, content_ids?: array<int, string>, content_type?: string, order_id?: string}
     */
    private function orderContent(Order $order): array
    {
        if ($order->exists) {
            $order->loadMissing('items.product');
        }

        $items = $order->relationLoaded('items') ? $order->items : collect();

        $names = $items
            ->map(fn ($item) => $item->product?->name)
            ->filter()
            ->unique()
            ->values();

        $ids = $items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_filter([
            'content_name' => $names->isEmpty() ? null : $names->implode(', '),
            'content_ids' => $ids === [] ? null : $ids,
            'content_type' => 'product',
            'order_id' => $order->order_number,
        ]);
    }

    private function orderUserData(Order $order): array
    {
        if ($order->exists) {
            $order->loadMissing(['user', 'salesFormSubmission']);
        }

        $user = $order->relationLoaded('user') ? $order->user : null;
        $submission = $order->relationLoaded('salesFormSubmission') ? $order->salesFormSubmission : null;

        return [
            'em' => $this->hash($order->billing_email ?: $user?->email),
            'ph' => $this->hashPhone(
                $order->phone_number
                ?: $submission?->phone
                ?: $user?->whatsapp_number
                ?: $user?->phone
            ),
            'external_id' => $this->hash((string) $order->user_id),
        ];
    }

    public function browserEvent(string $name, string $eventId, array $customData, array $browserData): void
    {
        $this->send($name, $eventId, $customData, [
            'client_ip_address' => $browserData['ip'] ?? null,
            'client_user_agent' => $browserData['user_agent'] ?? null,
            'fbp' => $browserData['fbp'] ?? null,
            'fbc' => $browserData['fbc'] ?? null,
        ], $browserData['url'] ?? null);
    }

    private function send(string $name, string $eventId, array $customData, array $userData, ?string $url = null): void
    {
        $pixelId = config('services.meta_conversions.pixel_id');
        $token = config('services.meta_conversions.access_token');
        $version = config('services.meta_conversions.api_version');
        if (! $pixelId || ! $token) {
            return;
        }

        $event = [
            'event_name' => $name,
            'event_time' => time(),
            'event_id' => $eventId,
            'action_source' => 'website',
            'user_data' => array_filter($userData, fn ($value) => $value !== null && $value !== ''),
        ];
        if ($customData !== []) {
            $event['custom_data'] = $customData;
        }
        if ($url) {
            $event['event_source_url'] = $url;
        }

        try {
            $endpoint = 'https://graph.facebook.com/'.$version.'/'.$pixelId.'/events?'.http_build_query(['access_token' => $token]);
            $response = Http::timeout(6)->post($endpoint, ['data' => [$event]]);
            if (! $response->successful()) {
                Log::warning('Meta Conversions API rejected event', ['event' => $name, 'status' => $response->status()]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Meta Conversions API request failed', ['event' => $name, 'exception' => $exception::class]);
        }
    }

    private function hash(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return $value === '' || $value === '0' ? null : hash('sha256', $value);
    }

    private function hashPhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : hash('sha256', $digits);
    }
}
