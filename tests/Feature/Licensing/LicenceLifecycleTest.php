<?php

namespace Tests\Feature\Licensing;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Licensing\LicenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * KURSA licensing (Phase 4) — environment-licence lifecycle (doc §5, §12, §14).
 */
class LicenceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function makeEnvironment(): Environment
    {
        $user = User::factory()->create();

        return Environment::create([
            'name' => 'Lifecycle Env',
            'primary_domain' => 'lifecycle-' . uniqid() . '.example.com',
            'owner_id' => $user->id,
        ]);
    }

    private function service(): LicenceService
    {
        return app(LicenceService::class);
    }

    /** @test */
    public function white_label_trial_persists_exactly_14_days(): void
    {
        $env = $this->makeEnvironment();

        $licence = $this->service()->startWhiteLabelTrial($env);

        $this->assertSame(EnvironmentLicence::STATUS_TRIALING, $licence->status);
        $this->assertSame(EnvironmentLicence::PLAN_WHITE_LABEL, $licence->plan_type);
        $this->assertNotNull($licence->trial_used_at);
        $this->assertNotNull($licence->trial_ends_at);

        // Exactly 14 days from the start.
        $this->assertEqualsWithDelta(
            $licence->starts_at->copy()->addDays(14)->timestamp,
            $licence->trial_ends_at->timestamp,
            2
        );
    }

    /** @test */
    public function only_one_trial_is_allowed_per_environment(): void
    {
        $env = $this->makeEnvironment();
        $this->service()->startWhiteLabelTrial($env);

        // Even after the licence leaves the trialing state, a second trial is refused.
        $licence = $env->licence()->first();
        $this->service()->downgradeToFree($licence);

        $this->expectException(\DomainException::class);
        $this->service()->startWhiteLabelTrial($env->fresh());
    }

    /** @test */
    public function trial_expiry_downgrades_safely_to_free_without_deleting_data(): void
    {
        Mail::fake();
        $env = $this->makeEnvironment();
        $licence = $this->service()->startWhiteLabelTrial($env);

        // Force the trial into the past.
        $licence->trial_ends_at = now()->subDay();
        $licence->saveQuietly();

        $this->artisan('licences:process-lifecycle')->assertExitCode(0);

        $licence->refresh();
        $this->assertSame(EnvironmentLicence::PLAN_FREE, $licence->plan_type);
        $this->assertSame(EnvironmentLicence::STATUS_FREE_ACTIVE, $licence->status);
        $this->assertNull($licence->ends_at); // Free never expires (§4.1)
        // Trial consumption is permanent even after downgrade (§5).
        $this->assertNotNull($licence->trial_used_at);
        // Data preserved: the environment (and its owner) still exist.
        $this->assertDatabaseHas('environments', ['id' => $env->id]);
    }

    /** @test */
    public function renewal_extends_from_max_of_now_and_current_ends_at(): void
    {
        $env = $this->makeEnvironment();

        // Active White Label with a future ends_at → extend from ends_at.
        $future = now()->addDays(30);
        $licence = EnvironmentLicence::create([
            'environment_id' => $env->id,
            'plan_type' => EnvironmentLicence::PLAN_WHITE_LABEL,
            'status' => EnvironmentLicence::STATUS_WHITE_LABEL_ACTIVE,
            'starts_at' => now()->subMonths(11),
            'ends_at' => $future,
        ]);

        $this->service()->renew($licence);
        $licence->refresh();

        $this->assertEqualsWithDelta(
            $future->copy()->addYear()->timestamp,
            $licence->ends_at->timestamp,
            2
        );

        // Expired licence → extend from now, not from the stale past ends_at.
        $licence->ends_at = now()->subDays(10);
        $licence->saveQuietly();

        $this->service()->renew($licence);
        $licence->refresh();

        $this->assertEqualsWithDelta(
            now()->addYear()->timestamp,
            $licence->ends_at->timestamp,
            2
        );
    }

    /** @test */
    public function cancellation_preserves_access_through_ends_at(): void
    {
        $env = $this->makeEnvironment();
        $endsAt = now()->addMonths(6);

        $licence = EnvironmentLicence::create([
            'environment_id' => $env->id,
            'plan_type' => EnvironmentLicence::PLAN_WHITE_LABEL,
            'status' => EnvironmentLicence::STATUS_WHITE_LABEL_ACTIVE,
            'starts_at' => now(),
            'ends_at' => $endsAt,
        ]);

        $this->service()->cancelAtPeriodEnd($licence);
        $licence->refresh();

        $this->assertTrue($licence->cancel_at_period_end);
        $this->assertSame(EnvironmentLicence::STATUS_CANCEL_AT_PERIOD_END, $licence->status);
        // Access window is untouched — still runs to the original period end.
        $this->assertEqualsWithDelta($endsAt->timestamp, $licence->ends_at->timestamp, 2);
        $this->assertTrue($licence->ends_at->isFuture());
    }

    /** @test */
    public function payment_initiation_alone_does_not_activate_a_paid_licence(): void
    {
        $env = $this->makeEnvironment();
        $this->service()->startFreeForever($env);

        // Creating a checkout intent (the "initiation" artefact) must NOT grant paid access.
        $this->service()->createCheckout([
            'plan_type' => EnvironmentLicence::PLAN_CREATOR,
            'environment' => $env,
        ]);

        $licence = $env->licence()->first();
        $this->assertSame(EnvironmentLicence::STATUS_FREE_ACTIVE, $licence->status);
        $this->assertSame(EnvironmentLicence::PLAN_FREE, $licence->plan_type);
    }

    /** @test */
    public function grace_window_elapsing_downgrades_to_free(): void
    {
        Mail::fake();
        $env = $this->makeEnvironment();

        $licence = EnvironmentLicence::create([
            'environment_id' => $env->id,
            'plan_type' => EnvironmentLicence::PLAN_CREATOR,
            'status' => EnvironmentLicence::STATUS_PAST_DUE,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
            'grace_ends_at' => now()->subDay(),
        ]);

        $this->artisan('licences:process-lifecycle')->assertExitCode(0);

        $licence->refresh();
        $this->assertSame(EnvironmentLicence::PLAN_FREE, $licence->plan_type);
        $this->assertSame(EnvironmentLicence::STATUS_FREE_ACTIVE, $licence->status);
    }
}
