<?php

namespace App\Support;

use Illuminate\Support\Facades\App;

/**
 * Paces outbound WhatsApp messages so bulk sends look like a human typing,
 * not a bot blasting the API. WhatsApp flags/bans numbers that send many
 * messages at a fixed, sub-second cadence, so each send is separated by a
 * randomised, multi-second delay (jitter) drawn from a configurable range.
 *
 * Ported from the shopikat project.
 */
class WhatsAppThrottle
{
    public static function minMs(): int
    {
        return max(0, (int) config('services.wachap.min_delay_ms', 4000));
    }

    public static function maxMs(): int
    {
        return max(self::minMs(), (int) config('services.wachap.max_delay_ms', 15000));
    }

    /**
     * Randomised, human-like delay (in microseconds) to wait before the next send.
     */
    public static function nextDelayMicroseconds(): int
    {
        return random_int(self::minMs(), self::maxMs()) * 1000;
    }

    /**
     * Pause between two WhatsApp sends. No-op during tests so the suite stays fast.
     */
    public static function pause(): void
    {
        if (App::runningUnitTests()) {
            return;
        }

        usleep(self::nextDelayMicroseconds());
    }
}
