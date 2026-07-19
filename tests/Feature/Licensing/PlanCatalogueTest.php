<?php

namespace Tests\Feature\Licensing;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCatalogueTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_the_three_kursa_licence_plans_with_exact_prices()
    {
        $free = Plan::where('type', 'free_forever')->first();
        $creator = Plan::where('type', 'creator_monthly')->first();
        $whiteLabel = Plan::where('type', 'white_label_annual')->first();

        $this->assertNotNull($free);
        $this->assertNotNull($creator);
        $this->assertNotNull($whiteLabel);

        $this->assertSame('0.00', $free->price_monthly);
        $this->assertSame('0.00', $free->price_annual);

        $this->assertSame('20.00', $creator->price_monthly);

        $this->assertSame('500.00', $whiteLabel->price_annual);

        // Setup fee is zero for all licence plans (doc §13.1).
        $this->assertSame('0.00', $free->setup_fee);
        $this->assertSame('0.00', $creator->setup_fee);
        $this->assertSame('0.00', $whiteLabel->setup_fee);
    }

    /** @test */
    public function free_forever_has_the_correct_limits()
    {
        $free = Plan::where('type', 'free_forever')->first();

        $this->assertSame(1, $free->getLimit('published_courses'));
        $this->assertSame(100, $free->getLimit('active_learners'));
    }

    /** @test */
    public function creator_has_the_correct_limits_and_sales_forms_feature()
    {
        $creator = Plan::where('type', 'creator_monthly')->first();

        $this->assertSame(10, $creator->getLimit('published_courses'));
        $this->assertSame(1000, $creator->getLimit('active_learners'));
        $this->assertTrue($creator->hasFeature('sales_forms'));
    }

    /** @test */
    public function white_label_has_unlimited_courses_and_full_branding_removal()
    {
        $whiteLabel = Plan::where('type', 'white_label_annual')->first();

        $this->assertNull($whiteLabel->getLimit('published_courses'));
        $this->assertSame(5000, $whiteLabel->getLimit('active_learners'));
        $this->assertTrue($whiteLabel->hasFeature('custom_domain'));
        $this->assertSame('removed', $whiteLabel->featureLevel('kursa_branding'));
        $this->assertSame(14, $whiteLabel->features['trial_days']);
    }

    /** @test */
    public function running_the_data_migration_logic_twice_is_idempotent()
    {
        $this->assertSame(1, Plan::where('type', 'free_forever')->count());

        // Re-run the same updateOrCreate logic the migration uses.
        Plan::updateOrCreate(
            ['type' => 'free_forever'],
            [
                'name' => 'Free Forever',
                'price_monthly' => 0,
                'price_annual' => 0,
                'setup_fee' => 0,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $this->assertSame(1, Plan::where('type', 'free_forever')->count());
    }
}
