<?php

namespace Tests\Unit;

use App\Support\WhatsAppThrottle;
use Tests\TestCase;

class WhatsAppThrottleTest extends TestCase
{
    public function test_delay_falls_within_the_configured_range(): void
    {
        config()->set('services.wachap.min_delay_ms', 4000);
        config()->set('services.wachap.max_delay_ms', 15000);

        for ($i = 0; $i < 100; $i++) {
            $micros = WhatsAppThrottle::nextDelayMicroseconds();

            $this->assertGreaterThanOrEqual(4000 * 1000, $micros);
            $this->assertLessThanOrEqual(15000 * 1000, $micros);
        }
    }

    public function test_delays_vary_so_sending_is_not_robotic(): void
    {
        config()->set('services.wachap.min_delay_ms', 1000);
        config()->set('services.wachap.max_delay_ms', 20000);

        $values = [];
        for ($i = 0; $i < 50; $i++) {
            $values[$i] = WhatsAppThrottle::nextDelayMicroseconds();
        }

        // A fixed-cadence sender would produce one repeated value; jitter should not.
        $this->assertGreaterThan(1, count(array_unique($values)));
    }

    public function test_inverted_config_is_clamped_to_a_valid_range(): void
    {
        config()->set('services.wachap.min_delay_ms', 9000);
        config()->set('services.wachap.max_delay_ms', 1000);

        $this->assertSame(9000, WhatsAppThrottle::minMs());
        $this->assertSame(9000, WhatsAppThrottle::maxMs());
        $this->assertSame(9000 * 1000, WhatsAppThrottle::nextDelayMicroseconds());
    }
}
