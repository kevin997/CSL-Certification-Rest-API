<?php

namespace App\Services;

use App\Models\Environment;
use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Models\Transaction;
use App\Support\Tenancy\TenantUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        $paidAt = $order->exists
            ? $order->transactions()->withoutGlobalScopes()
                ->where('status', Transaction::STATUS_COMPLETED)->where('total_amount', '>', 0)
                ->orderBy('paid_at')->first()?->paid_at
            : null;
        $eventTime = $paidAt ?? $order->updated_at ?? now();
        // Browser/server deduplication has a finite window. Preserve the actual
        // payment time and stop automated replays before that window expires.
        if ($eventTime->lt(now()->subHours(47))) {
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
                : null,
            $eventTime,
            $order
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
            $submission->loadMissing(['orders.items.product', 'salesForm.fields']);
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
            $this->submissionUserData($submission, $order),
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
            $order->loadMissing(['user', 'salesFormSubmission.salesForm.fields']);
        }

        $submission = $order->relationLoaded('salesFormSubmission') ? $order->salesFormSubmission : null;

        if ($submission) {
            return $this->submissionUserData($submission, $order);
        }

        $user = $order->relationLoaded('user') ? $order->user : null;

        return [
            'em' => $this->hash($order->billing_email ?: $user?->email),
            'ph' => $this->hashPhone(
                $order->phone_number
                ?: $user?->whatsapp_number
                ?: $user?->phone
            ),
            'external_id' => $this->hash((string) $order->user_id),
        ];
    }

    /**
     * High-coverage customer information for CAPI events. Values are hashed
     * once, with Meta's normalization, and fbp/fbc/IP/UA stay unhashed.
     */
    private function submissionUserData(SalesFormSubmission $submission, ?Order $order = null): array
    {
        if ($submission->exists) {
            $submission->loadMissing(['salesForm.fields', 'user']);
        }

        $answers = is_array($submission->answers) ? $submission->answers : [];
        $fields = $submission->relationLoaded('salesForm') ? $submission->salesForm?->fields : null;

        $name = trim((string) ($submission->name ?? ''));
        $nameParts = preg_split('/\s+/', $name, 2) ?: [];
        $firstName = $this->answerByKey($answers, $fields, ['first_name', 'firstname', 'prenom', 'prénom']) ?: ($nameParts[0] ?? null);
        $lastName = $this->answerByKey($answers, $fields, ['last_name', 'lastname', 'surname', 'nom_de_famille']) ?: ($nameParts[1] ?? null);
        $gender = $this->hashGender($this->answerByKey($answers, $fields, ['gender', 'sexe', 'sex']));
        $birthDate = $this->hashDate($this->answerByKey($answers, $fields, ['date_of_birth', 'dob', 'birth', 'naissance']));

        $user = [
            'em' => $this->hash($order?->billing_email ?: $submission->email ?: $submission->user?->email),
            'ph' => $this->hashPhone($order?->phone_number ?: $submission->phone ?: $submission->user?->whatsapp_number),
            'fn' => $this->hashText($firstName),
            'ln' => $this->hashText($lastName),
            'ct' => $this->hashText($this->answerByType($answers, $fields, 'city')),
            'st' => $this->hashText($this->answerByType($answers, $fields, 'state')),
            'country' => $this->hashText($this->answerByType($answers, $fields, 'country')),
            'zp' => $this->hashText($this->answerByKey($answers, $fields, ['postal', 'zip', 'code_postal', 'postcode'])),
            'db' => $birthDate,
            'ge' => $gender,
            'external_id' => $this->hash((string) ($submission->user_id ?? $order?->user_id)),
            'fbp' => $submission->meta_fbp ?: null,
            'fbc' => $submission->meta_fbc ?: null,
            'client_ip_address' => $submission->client_ip ?: null,
            'client_user_agent' => $submission->client_user_agent ?: null,
        ];

        return array_filter($user, fn ($value) => $value !== null && $value !== '');
    }

    private function answerByType(array $answers, $fields, string $type): ?string
    {
        if (! $fields) {
            return null;
        }

        $field = $fields->firstWhere('type', $type);
        $value = $field ? ($answers[$field->field_key] ?? null) : null;

        return is_scalar($value) ? (string) $value : null;
    }

    private function answerByKey(array $answers, $fields, array $patterns): ?string
    {
        if (! $fields) {
            return null;
        }

        $field = $fields->first(function ($field) use ($patterns) {
            $key = strtolower((string) $field->field_key);

            foreach ($patterns as $pattern) {
                if (str_contains($key, $pattern)) {
                    return true;
                }
            }

            return false;
        });

        $value = $field ? ($answers[$field->field_key] ?? null) : null;

        return is_scalar($value) ? (string) $value : null;
    }

    private function hashText(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^\p{L}\p{N} ]+/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value === '' ? null : hash('sha256', $value);
    }

    private function hashDate(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return strlen($digits) === 8 ? hash('sha256', $digits) : null;
    }

    private function hashGender(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return match (true) {
            in_array($value, ['m', 'male', 'homme', 'masculin'], true) => hash('sha256', 'm'),
            in_array($value, ['f', 'female', 'femme', 'feminin', 'féminin'], true) => hash('sha256', 'f'),
            default => null,
        };
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

    private function send(string $name, string $eventId, array $customData, array $userData, ?string $url = null, ?CarbonInterface $eventTime = null, ?Order $purchase = null): void
    {
        $pixelIds = $this->pixelIds();
        $token = config('services.meta_conversions.access_token');
        $version = config('services.meta_conversions.api_version');
        if ($pixelIds === [] || ! $token) {
            return;
        }

        $event = [
            'event_name' => $name,
            'event_time' => ($eventTime ?? now())->getTimestamp(),
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

        $failed = false;
        foreach ($pixelIds as $pixelId) {
            $delivery = $purchase?->exists ? $this->claimPurchase($purchase, $pixelId, $eventTime ?? now()) : null;
            if ($purchase?->exists && $delivery === null) {
                continue;
            }
            if ($delivery !== null) {
                $event['event_time'] = Carbon::parse($delivery->event_time)->getTimestamp();
            }
            $status = null;
            $errorCode = null;
            $accepted = false;
            try {
                $endpoint = 'https://graph.facebook.com/'.$version.'/'.$pixelId.'/events';
                $response = Http::withToken($token)->timeout(6)->post($endpoint, ['data' => [$event]]);
                $status = $response->status();
                $accepted = $response->successful() && (int) $response->json('events_received', 0) > 0;
                $errorCode = is_numeric($response->json('error.code')) ? (int) $response->json('error.code') : null;
            } catch (\Throwable $exception) {
                Log::warning('Meta Conversions API request failed', [
                    'event' => $name,
                    'pixel_id' => $pixelId,
                    'exception' => $exception::class,
                ]);
            }
            if ($delivery !== null) {
                DB::table('meta_purchase_deliveries')->where('id', $delivery->id)
                    ->where('lease_token', $delivery->lease_token)->update([
                        'lease_token' => null,
                        'delivered_at' => $accepted ? now() : null,
                        'last_http_status' => $status,
                        'last_error_code' => $errorCode,
                        'available_at' => now()->addSeconds(min(3600, 30 * 2 ** min($delivery->attempts, 7))),
                        'updated_at' => now(),
                    ]);
            }
            if (! $accepted) {
                $failed = true;
                Log::warning('Meta Conversions API rejected event', [
                    'event' => $name, 'event_id' => $eventId, 'pixel_id' => $pixelId,
                    'status' => $status, 'error_code' => $errorCode,
                ]);
            }
        }
        if ($failed && $purchase !== null) {
            // Fail the queued listener only after every configured pixel gets
            // its attempt. Previously these failures silently completed jobs.
            throw new \RuntimeException('Meta Purchase delivery is pending retry');
        }
    }

    private function claimPurchase(Order $order, string $pixelId, CarbonInterface $eventTime): ?object
    {
        DB::table('meta_purchase_deliveries')->insertOrIgnore([
            'order_id' => $order->id, 'pixel_id' => $pixelId,
            'event_time' => $eventTime, 'available_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $lease = (string) Str::uuid();
        $claimed = DB::table('meta_purchase_deliveries')->where('order_id', $order->id)
            ->where('pixel_id', $pixelId)->whereNull('delivered_at')
            ->where('available_at', '<=', now())->where('attempts', '<', 20)
            ->where('event_time', '>=', now()->subHours(47))
            ->update([
                'lease_token' => $lease, 'attempts' => DB::raw('attempts + 1'),
                'available_at' => now()->addMinute(), 'updated_at' => now(),
            ]);

        return $claimed ? DB::table('meta_purchase_deliveries')->where('lease_token', $lease)->first() : null;
    }

    /**
     * Every pixel id this deployment reports to, deduplicated.
     *
     * META_PIXEL_IDS is the multi-pixel setting; META_PIXEL_ID remains a
     * supported single-pixel fallback (and the built-in default) so existing
     * deployments do not silently lose tracking.
     *
     * @return list<string>
     */
    public function pixelIds(): array
    {
        $candidates = (array) config('services.meta_conversions.pixel_ids', []);

        $single = config('services.meta_conversions.pixel_id');
        if ($single !== null) {
            $candidates[] = $single;
        }

        $ids = [];
        foreach ($candidates as $candidate) {
            if (! is_string($candidate) && ! is_int($candidate)) {
                continue;
            }

            $id = trim((string) $candidate);
            if (preg_match('/^\d{5,20}$/', $id) === 1) {
                $ids[$id] = true;
            }
        }

        return array_map(strval(...), array_keys($ids));
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
