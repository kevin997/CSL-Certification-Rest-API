<?php

namespace Tests\Feature\Characterisation;

use App\Models\Environment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHARACTERISATION: locks in today's phantom-column bug in demo/supported
 * onboarding. DemoOnboardingController::store (~:152) and
 * SupportedOnboardingController (~:184) create a Subscription passing
 * start_date/end_date/is_trial — but Subscription::$fillable only knows
 * starts_at/ends_at/trial_ends_at, so Eloquent mass assignment silently
 * drops the unknown keys. The result: a "14-day trial" subscription is
 * persisted with no expiry at all. Phase 4 replaces this with a dedicated
 * environment_licences table and LicenceService that writes the correct
 * columns.
 */
class SubscriptionPhantomColumnTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function creating_a_subscription_with_start_date_end_date_and_is_trial_silently_drops_them()
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

        // CHARACTERISATION: current behavior — Phase N will flip this expectation.
        // This mirrors exactly what DemoOnboardingController::store does today:
        // it passes start_date/end_date/is_trial, none of which are fillable
        // on Subscription.
        $subscription = Subscription::create([
            'plan_id' => $plan->id,
            'environment_id' => $environment->id,
            'billing_cycle' => 'monthly',
            'start_date' => now(),
            'end_date' => $expiresAt,
            'status' => Subscription::STATUS_TRIAL,
            'is_trial' => true,
        ]);

        $subscription->refresh();

        // The phantom columns were never persisted at all (they aren't even
        // real database columns).
        $this->assertArrayNotHasKey('start_date', $subscription->getAttributes());
        $this->assertArrayNotHasKey('end_date', $subscription->getAttributes());
        $this->assertArrayNotHasKey('is_trial', $subscription->getAttributes());

        // And the real columns that WOULD express a trial expiry were never
        // set either — the "14-day trial" never actually expires.
        $this->assertNull($subscription->ends_at);
        $this->assertNull($subscription->trial_ends_at);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'ends_at' => null,
            'trial_ends_at' => null,
        ]);
    }
}
