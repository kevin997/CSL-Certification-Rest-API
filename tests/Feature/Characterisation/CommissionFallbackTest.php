<?php

namespace Tests\Feature\Characterisation;

use App\Services\Commission\CommissionService;
use App\Services\EnvironmentPaymentConfigService;
use App\Services\Tax\TaxZoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHARACTERISATION: locks in today's 17% commission fallback and its
 * commission-inclusive reverse calculation. CommissionService::
 * extractCommissionFromProductPrice (~:82-98) falls back to a hardcoded 17%
 * rate whenever no active Commission record exists, and treats the input
 * price as ALREADY including commission (reverse-dividing by 1 + rate).
 * Phase 2 of the KURSA licensing transition plan deletes the 17% fallback
 * entirely and stops charging course commissions.
 */
class CommissionFallbackTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function with_no_commission_record_it_falls_back_to_17_percent_inclusive_reverse_calculation()
    {
        // No Commission rows exist in the database at all.
        $service = app(CommissionService::class);

        $result = $service->extractCommissionFromProductPrice(117.0, null);

        // CHARACTERISATION: current behavior — Phase N will flip this expectation.
        // 117 is treated as (originalPrice * 1.17), so originalPrice = 100
        // and commission = 17, using the hardcoded 17% default rate.
        $this->assertEquals(17.0, $result['commission_rate']);
        $this->assertEqualsWithDelta(100.0, $result['original_price'], 0.001);
        $this->assertEqualsWithDelta(17.0, $result['commission_amount'], 0.001);
    }
}
