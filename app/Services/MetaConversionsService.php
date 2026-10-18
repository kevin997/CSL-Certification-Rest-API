<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaConversionsService
{
    public function purchase(Order $order): void
    {
        if ($order->status !== Order::STATUS_COMPLETED || $order->total_amount <= 0) {
            return;
        }

        $this->send('Purchase', 'purchase:order:'.$order->id, [
            'value' => (float) $order->total_amount,
            'currency' => $order->currency,
            'order_id' => $order->order_number,
        ], [
            'em' => $this->hash($order->billing_email ?: $order->user?->email),
            'ph' => $this->hashPhone($order->salesFormSubmission?->phone ?: $order->user?->phone),
            'external_id' => $this->hash((string) $order->user_id),
        ], $order->environment ? TenantUrl::to($order->environment) : null);
    }

    public function registration(SalesFormSubmission $submission): void
    {
        $this->send('CompleteRegistration', 'registration:submission:'.$submission->id, [], [
            'em' => $this->hash($submission->email),
            'ph' => $this->hashPhone($submission->phone),
            'external_id' => $this->hash((string) $submission->user_id),
        ]);
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
