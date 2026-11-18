<?php

namespace App\Services\Sales;

use App\Models\Order;
use App\Models\ShortLink;
use App\Support\Tenancy\TenantUrl;

/**
 * Create and resolve short branded checkout links.
 *
 * The message carries only `/p/{code}`; the destination is rebuilt on click,
 * so the encrypted checkout token in the URL cannot go stale in an old
 * WhatsApp message and nothing long is exposed to the recipient.
 */
class ShortLinkService
{
    /** No i/l/o/0/1: easier to read back from a chat. */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const CODE_LENGTH = 10;

    private const LIFETIME_DAYS = 30;

    public function __construct(
        private readonly OrderContinueUrl $continueUrl,
    ) {}

    public function forOrder(Order $order): string
    {
        if ($order->status !== Order::STATUS_PENDING) {
            return '';
        }

        $order->loadMissing('environment');

        if (! $order->environment) {
            return '';
        }

        $link = ShortLink::query()
            ->where('order_id', $order->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        if (! $link) {
            $link = ShortLink::create([
                'code' => $this->uniqueCode(),
                'order_id' => $order->id,
                'environment_id' => $order->environment_id,
                'expires_at' => now()->addDays(self::LIFETIME_DAYS),
            ]);
        }

        return TenantUrl::to($order->environment, '/p/'.$link->code);
    }

    public function resolve(string $code): ?string
    {
        $link = ShortLink::query()
            ->where('code', $code)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $link) {
            return null;
        }

        $order = Order::withoutGlobalScopes()
            ->with(['environment', 'salesFormSubmission.salesForm'])
            ->find($link->order_id);

        if (! $order || $order->status !== Order::STATUS_PENDING) {
            return null;
        }

        $link->increment('hits');

        return $this->continueUrl->for($order);
    }

    private function uniqueCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = $this->randomCode();

            if (! ShortLink::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not allocate a unique short link code.');
    }

    private function randomCode(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
