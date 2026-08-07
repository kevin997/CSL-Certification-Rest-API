<?php

namespace Tests\Feature\Licensing;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KURSA licensing transition — Phase 8 (doc §13 Phase 8). Exercises
 * `licences:migrate-environments` against a mix of legacy environments and
 * asserts every mapping bucket, idempotency, --dry-run side-effect-freedom,
 * --environment-ids scoping, and --deactivate-legacy-plans.
 */
class MigrationCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(string $type): Plan
    {
        return Plan::create([
            'name' => ucfirst($type),
            'type' => $type,
            'price_monthly' => 0,
            'price_annual' => 0,
            'setup_fee' => 0,
            'is_active' => true,
        ]);
    }

    private function makeEnvironment(string $name): Environment
    {
        $owner = User::factory()->create();

        return Environment::create([
            'name' => $name,
            'primary_domain' => str()->slug($name) . '.example.com',
            'owner_id' => $owner->id,
        ]);
    }

    /** @test */
    public function it_migrates_every_legacy_bucket_correctly()
    {
        // Phase 1 catalogue plans, required for plan_id resolution.
        $this->makePlan('free_forever');
        $this->makePlan('creator_monthly');
        $this->makePlan('white_label_annual');

        $standalonePlan = $this->makePlan('standalone');
        $demoPlan = $this->makePlan('demo');
        $supportedPlan = $this->makePlan('supported');
        $businessPlan = $this->makePlan('business');

        // 1) standalone env → free_forever / free_active.
        $standaloneEnv = $this->makeEnvironment('Standalone Env');
        Subscription::create([
            'plan_id' => $standalonePlan->id,
            'environment_id' => $standaloneEnv->id,
            'billing_cycle' => 'monthly',
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subMonths(2),
        ]);

        // 2) active demo (future trial window) → white_label_annual / trialing.
        $activeDemoEnv = $this->makeEnvironment('Active Demo Env');
        Subscription::create([
            'plan_id' => $demoPlan->id,
            'environment_id' => $activeDemoEnv->id,
            'billing_cycle' => 'monthly',
            'status' => Subscription::STATUS_TRIAL,
            'starts_at' => now()->subDays(2),
            'trial_ends_at' => now()->addDays(5),
        ]);

        // 3) expired demo → free_forever / free_active.
        $expiredDemoEnv = $this->makeEnvironment('Expired Demo Env');
        Subscription::create([
            'plan_id' => $demoPlan->id,
            'environment_id' => $expiredDemoEnv->id,
            'billing_cycle' => 'monthly',
            'status' => Subscription::STATUS_EXPIRED,
            'starts_at' => now()->subDays(30),
            'trial_ends_at' => now()->subDays(16),
        ]);

        // 4) active supported env, next_payment_at in the future → white_label_annual / white_label_active.
        $activeSupportedEnv = $this->makeEnvironment('Active Supported Env');
        $nextPayment = now()->addDays(20);
        Subscription::create([
            'plan_id' => $supportedPlan->id,
            'environment_id' => $activeSupportedEnv->id,
            'billing_cycle' => 'monthly',
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subMonths(3),
            'next_payment_at' => $nextPayment,
            'last_payment_at' => now()->subDays(10),
        ]);

        // 5) canceled business env → free_forever / free_active.
        $canceledBusinessEnv = $this->makeEnvironment('Canceled Business Env');
        Subscription::create([
            'plan_id' => $businessPlan->id,
            'environment_id' => $canceledBusinessEnv->id,
            'billing_cycle' => 'annual',
            'status' => Subscription::STATUS_CANCELED,
            'starts_at' => now()->subYear(),
        ]);

        // 6) no subscription at all → free_forever / free_active.
        $noSubEnv = $this->makeEnvironment('No Subscription Env');

        // 7) already licenced env → untouched, idempotent.
        $alreadyLicencedEnv = $this->makeEnvironment('Already Licenced Env');
        $existingLicence = EnvironmentLicence::create([
            'environment_id' => $alreadyLicencedEnv->id,
            'plan_type' => EnvironmentLicence::PLAN_FREE,
            'status' => EnvironmentLicence::STATUS_FREE_ACTIVE,
            'starts_at' => now()->subDay(),
        ]);

        // --- dry run must not write anything ---
        $this->artisan('licences:migrate-environments', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('environment_licences', 1); // only the pre-existing one

        // --- real run ---
        $this->artisan('licences:migrate-environments')
            ->assertExitCode(0);

        $this->assertDatabaseCount('environment_licences', 7);

        $standaloneLicence = EnvironmentLicence::where('environment_id', $standaloneEnv->id)->first();
        $this->assertSame(EnvironmentLicence::PLAN_FREE, $standaloneLicence->plan_type);
        $this->assertSame(EnvironmentLicence::STATUS_FREE_ACTIVE, $standaloneLicence->status);
        $this->assertNull($standaloneLicence->ends_at);
        $this->assertSame('standalone', $standaloneLicence->price_snapshot['migrated_from']);

        $activeDemoLicence = EnvironmentLicence::where('environment_id', $activeDemoEnv->id)->first();
        $this->assertSame(EnvironmentLicence::PLAN_WHITE_LABEL, $activeDemoLicence->plan_type);
        $this->assertSame(EnvironmentLicence::STATUS_TRIALING, $activeDemoLicence->status);
        $this->assertNotNull($activeDemoLicence->trial_ends_at);
        $this->assertNotNull($activeDemoLicence->trial_used_at);
        $this->assertEqualsWithDelta(
            now()->addDays(14)->timestamp,
            $activeDemoLicence->trial_ends_at->timestamp,
            5
        );

        $expiredDemoLicence = EnvironmentLicence::where('environment_id', $expiredDemoEnv->id)->first();
        $this->assertSame(EnvironmentLicence::PLAN_FREE, $expiredDemoLicence->plan_type);
        $this->assertSame(EnvironmentLicence::STATUS_FREE_ACTIVE, $expiredDemoLicence->status);

        $activeSupportedLicence = EnvironmentLicence::where('environment_id', $activeSupportedEnv->id)->first();
        $this->assertSame(EnvironmentLicence::PLAN_WHITE_LABEL, $activeSupportedLicence->plan_type);
        $this->assertSame(EnvironmentLicence::STATUS_WHITE_LABEL_ACTIVE, $activeSupportedLicence->status);
        $this->assertNotNull($activeSupportedLicence->ends_at);
        $this->assertEqualsWithDelta($nextPayment->timestamp, $activeSupportedLicence->ends_at->timestamp, 5);
        $this->assertSame('supported', $activeSupportedLicence->price_snapshot['migrated_from']);

        $canceledBusinessLicence = EnvironmentLicence::where('environment_id', $canceledBusinessEnv->id)->first();
        $this->assertSame(EnvironmentLicence::PLAN_FREE, $canceledBusinessLicence->plan_type);
        $this->assertSame(EnvironmentLicence::STATUS_FREE_ACTIVE, $canceledBusinessLicence->status);

        $noSubLicence = EnvironmentLicence::where('environment_id', $noSubEnv->id)->first();
        $this->assertSame(EnvironmentLicence::PLAN_FREE, $noSubLicence->plan_type);
        $this->assertSame(EnvironmentLicence::STATUS_FREE_ACTIVE, $noSubLicence->status);

        // Already-licenced env untouched.
        $alreadyLicencedEnv->refresh();
        $this->assertSame(
            $existingLicence->starts_at->timestamp,
            EnvironmentLicence::where('environment_id', $alreadyLicencedEnv->id)->first()->starts_at->timestamp
        );

        // --- idempotency: second run changes nothing ---
        $before = EnvironmentLicence::orderBy('id')->get()->toArray();

        $this->artisan('licences:migrate-environments')
            ->assertExitCode(0);

        $this->assertDatabaseCount('environment_licences', 7);
        $after = EnvironmentLicence::orderBy('id')->get()->toArray();
        $this->assertEquals($before, $after);
    }

    /** @test */
    public function dry_run_writes_nothing_even_with_a_flagged_warn_case()
    {
        $this->makePlan('free_forever');
        $this->makePlan('white_label_annual');
        $supportedPlan = $this->makePlan('supported');

        $env = $this->makeEnvironment('Underivable Period Env');
        // Active but with no next_payment_at/last_payment_at → WARN → now+1yr.
        Subscription::create([
            'plan_id' => $supportedPlan->id,
            'environment_id' => $env->id,
            'billing_cycle' => 'monthly',
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subMonths(2),
        ]);

        $this->artisan('licences:migrate-environments', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('environment_licences', 0);

        $this->artisan('licences:migrate-environments')
            ->assertExitCode(0);

        $licence = EnvironmentLicence::where('environment_id', $env->id)->first();
        $this->assertSame(EnvironmentLicence::PLAN_WHITE_LABEL, $licence->plan_type);
        $this->assertEqualsWithDelta(now()->addYear()->timestamp, $licence->ends_at->timestamp, 5);
        $this->assertStringContainsString(
            'now + 1 year — no derivable period end, flagged for manual review',
            $licence->price_snapshot['derivation']
        );
    }

    /** @test */
    public function environment_ids_option_restricts_scope()
    {
        $this->makePlan('free_forever');
        $this->makePlan('white_label_annual');
        $standalonePlan = $this->makePlan('standalone');

        $envA = $this->makeEnvironment('Env A');
        $envB = $this->makeEnvironment('Env B');

        Subscription::create([
            'plan_id' => $standalonePlan->id,
            'environment_id' => $envA->id,
            'billing_cycle' => 'monthly',
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        Subscription::create([
            'plan_id' => $standalonePlan->id,
            'environment_id' => $envB->id,
            'billing_cycle' => 'monthly',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->artisan('licences:migrate-environments', ['--environment-ids' => (string) $envA->id])
            ->assertExitCode(0);

        $this->assertDatabaseCount('environment_licences', 1);
        $this->assertDatabaseHas('environment_licences', ['environment_id' => $envA->id]);
        $this->assertDatabaseMissing('environment_licences', ['environment_id' => $envB->id]);
    }

    /** @test */
    public function deactivate_legacy_plans_flag_deactivates_only_legacy_plans()
    {
        $this->makePlan('free_forever');
        $this->makePlan('creator_monthly');
        $this->makePlan('white_label_annual');
        $standalonePlan = $this->makePlan('standalone');
        $demoPlan = $this->makePlan('demo');

        // Dry run must NOT deactivate, even with the flag passed.
        $this->artisan('licences:migrate-environments', [
            '--dry-run' => true,
            '--deactivate-legacy-plans' => true,
        ])->assertExitCode(0);

        $this->assertTrue($standalonePlan->fresh()->is_active);
        $this->assertTrue($demoPlan->fresh()->is_active);

        $this->artisan('licences:migrate-environments', ['--deactivate-legacy-plans' => true])
            ->assertExitCode(0);

        $this->assertFalse($standalonePlan->fresh()->is_active);
        $this->assertFalse($demoPlan->fresh()->is_active);
        $this->assertTrue(Plan::where('type', 'free_forever')->value('is_active'));
        $this->assertTrue(Plan::where('type', 'creator_monthly')->value('is_active'));
        $this->assertTrue(Plan::where('type', 'white_label_annual')->value('is_active'));
    }
}
