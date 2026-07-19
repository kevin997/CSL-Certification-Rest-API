<?php

namespace Tests\Feature\Characterisation;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Licensing\LicenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 4 FLIP: the demo/supported onboarding controllers previously wrote
 * phantom columns (start_date / end_date / is_trial) that Eloquent silently
 * dropped, so "14-day trials" never expired. They now write the REAL columns
 * (starts_at / ends_at / trial_ends_at) AND dual-write an authoritative
 * EnvironmentLicence. This test asserts the corrected behaviour: a trial period
 * actually persists, and the environment licence carries a real 14-day expiry.
 */
class SubscriptionPhantomColumnTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function trial_subscription_and_licence_persist_a_real_14_day_expiry()
    {
        $owner = User::factory()->create();

        $environment = Environment::create([
            'name' => 'Demo Environment',
            'primary_domain' => 'demo.example.com',
            'slug' => 'demo-env',
            'owner_id' => $owner->id,
        ]);

        $plan = Plan::create([
            'name' => 'Demo Plan',
            'type' => 'business_teacher',
            'price_monthly' => 0,
            'price_annual' => 0,
            'setup_fee' => 0,
            'is_active' => true,
        ]);

        $expiresAt = now()->addDays(14);

        // PHASE 4 FLIP: this mirrors what the FIXED DemoOnboardingController now
        // writes — the real columns, which persist.
        $subscription = Subscription::create([
            'plan_id' => $plan->id,
            'environment_id' => $environment->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => $expiresAt,
            'trial_ends_at' => $expiresAt,
            'status' => Subscription::STATUS_TRIAL,
        ]);

        $subscription->refresh();

        // The real expiry columns are now persisted — the trial actually expires.
        $this->assertNotNull($subscription->ends_at);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertEqualsWithDelta($expiresAt->timestamp, $subscription->trial_ends_at->timestamp, 2);

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
        $this->assertNotNull(
            \DB::table('subscriptions')->where('id', $subscription->id)->value('trial_ends_at')
        );

        // And the authoritative dual-write: a White Label trial EnvironmentLicence
        // with a real 14-day trial_ends_at and a recorded trial consumption.
        $licence = app(LicenceService::class)->startWhiteLabelTrial($environment);

        $this->assertSame(EnvironmentLicence::STATUS_TRIALING, $licence->status);
        $this->assertNotNull($licence->trial_ends_at);
        $this->assertNotNull($licence->trial_used_at);
        $this->assertEqualsWithDelta(
            $licence->starts_at->copy()->addDays(14)->timestamp,
            $licence->trial_ends_at->timestamp,
            2
        );
    }
}
