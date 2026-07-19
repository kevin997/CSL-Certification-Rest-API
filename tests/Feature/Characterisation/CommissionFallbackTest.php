<?php

namespace Tests\Feature\Characterisation;

use App\Services\Commission\CommissionService;
use App\Services\EnvironmentPaymentConfigService;
use App\Services\Tax\TaxZoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 2 (flipped): this test used to characterise the OLD 17% commission
 * fallback and its commission-inclusive reverse calculation. As of the KURSA
 * licensing transition Phase 2, that behaviour is GONE:
 * CommissionService::extractCommissionFromProductPrice no longer falls back to
 * a hardcoded 17% rate and no longer reverse-divides by (1 + rate). Course
 * sales carry 0% platform commission and the price is the creator's selling
 * price, used as-is. The expectation below has been flipped accordingly.
 */
class CommissionFallbackTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function with_no_commission_record_it_returns_zero_commission_and_the_price_unchanged()
    {
        // No Commission rows exist in the database at all.
        $service = app(CommissionService::class);

        $result = $service->extractCommissionFromProductPrice(117.0, null);

        // PHASE 2 behavior: 0% commission, no reverse calculation. 117 stays 117,
        // the commission amount is 0 and the rate is 0 — the creator keeps the
        // full selling price.
        $this->assertEquals(0.0, $result['commission_rate']);
        $this->assertEqualsWithDelta(117.0, $result['original_price'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['commission_amount'], 0.001);
    }
}
