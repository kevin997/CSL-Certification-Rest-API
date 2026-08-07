<?php

namespace Tests\Feature\Entitlements;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * KURSA plan Phase 9 — entitlement enforcement matrix (doc §4.4 + §12).
 *
 * Every gate is exercised environment-scoped for the three canonical plans with
 * enforcement_enabled=true, plus the dark-deploy, grace, and over-limit-data
 * invariants. A gate "allows" when it does NOT emit its own 403 error code —
 * the request may still fail downstream for unrelated reasons (validation,
 * missing resource); that is not our concern here.
 */
class EnforcementMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['licensing.enforcement_enabled' => true]);
        Cache::flush();
    }

    // ---------------------------------------------------------------------
    // Harness
    // ---------------------------------------------------------------------

    private function makeEnv(string $planType, string $status): array
    {
        $owner = User::factory()->create();

        $env = Environment::create([
            'name' => 'Matrix Env',
            'primary_domain' => 'matrix-' . uniqid() . '.example.com',
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);

        $plan = Plan::where('type', $planType)->firstOrFail();

        EnvironmentLicence::create([
            'environment_id' => $env->id,
            'plan_id' => $plan->id,
            'plan_type' => $planType,
            'status' => $status,
            'starts_at' => now(),
            'ends_at' => $planType === EnvironmentLicence::PLAN_FREE ? null : now()->addYear(),
            'grace_ends_at' => in_array($status, [
                EnvironmentLicence::STATUS_GRACE,
                EnvironmentLicence::STATUS_PAST_DUE,
            ], true) ? now()->addDays(5) : null,
        ]);

        Cache::flush(); // ensure fresh entitlement + usage resolution

        return [$env, $owner];
    }

    private function free(string $status = EnvironmentLicence::STATUS_FREE_ACTIVE): array
    {
        return $this->makeEnv(EnvironmentLicence::PLAN_FREE, $status);
    }

    private function creator(string $status = EnvironmentLicence::STATUS_CREATOR_ACTIVE): array
    {
        return $this->makeEnv(EnvironmentLicence::PLAN_CREATOR, $status);
    }

    private function whiteLabel(string $status = EnvironmentLicence::STATUS_WHITE_LABEL_ACTIVE): array
    {
        return $this->makeEnv(EnvironmentLicence::PLAN_WHITE_LABEL, $status);
    }

    private function hit(string $method, string $path, Environment $env, ?User $actingAs, array $data = []): TestResponse
    {
        if ($actingAs) {
            Sanctum::actingAs($actingAs);
        }

        return $this->json($method, '/api' . $path, $data, [
            'X-Frontend-Domain' => $env->primary_domain,
        ]);
    }

    private function assertGateBlocks(TestResponse $response, string $errorCode): void
    {
        $response->assertStatus(403);
        $this->assertSame($errorCode, $response->json('error'),
            "Expected gate error '{$errorCode}', got: " . json_encode($response->json()));
    }

    private function assertGateAllows(TestResponse $response, string $errorCode): void
    {
        // The specific gate must NOT have fired. Any other outcome is fine.
        if ($response->status() === 403 && $response->json('error') === $errorCode) {
            $this->fail("Gate '{$errorCode}' fired but should have allowed the request.");
        }

        $this->assertTrue(true);
    }

    private function seedPublishedCourses(Environment $env, int $count): void
    {
        // A real template row is required (courses.template_id FK).
        $templateId = DB::table('templates')->insertGetId([
            'title' => 'T' . uniqid(),
            'status' => 'published',
            'created_by' => $env->owner_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'title' => 'C' . uniqid(),
                'template_id' => $templateId,
                'created_by' => $env->owner_id,
                'environment_id' => $env->id,
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('courses')->insert($rows);
    }

    private function seedPivots(Environment $env, int $count, string $role, bool $active): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create();
            $rows[] = [
                'environment_id' => $env->id,
                'user_id' => $user->id,
                'role' => $role,
                'last_active_at' => $active ? now() : null,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('environment_user')->insert($rows);
    }

    private function seedActiveLearners(Environment $env, int $count): void
    {
        $this->seedPivots($env, $count, 'learner', true);
    }

    private function seedSeats(Environment $env, int $count): void
    {
        $this->seedPivots($env, $count, 'admin', false);
    }

    private function seedActiveGateways(Environment $env, int $count): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'environment_id' => $env->id,
                'gateway_name' => 'GW' . $i,
                'code' => 'gw' . uniqid(),
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('payment_gateway_settings')->insert($rows);
    }

    // ---------------------------------------------------------------------
    // Published courses limit (1 / 10 / ∞)
    // ---------------------------------------------------------------------

    /** @test */
    public function free_second_course_publish_is_blocked_but_creator_under_limit_is_allowed(): void
    {
        [$freeEnv, $freeOwner] = $this->free();
        $this->seedPublishedCourses($freeEnv, 1); // at the Free limit of 1

        $blocked = $this->hit('POST', '/courses/999/publish', $freeEnv, $freeOwner);
        $this->assertGateBlocks($blocked, 'plan_limit_reached');

        [$creatorEnv, $creatorOwner] = $this->creator();
        $this->seedPublishedCourses($creatorEnv, 1); // well under Creator's 10

        $allowed = $this->hit('POST', '/courses/999/publish', $creatorEnv, $creatorOwner);
        $this->assertGateAllows($allowed, 'plan_limit_reached');
    }

    /** @test */
    public function over_limit_existing_published_courses_stay_readable_only_new_publish_blocked(): void
    {
        [$env, $owner] = $this->free();
        $this->seedPublishedCourses($env, 5); // migrated over-limit data

        $blocked = $this->hit('POST', '/courses/999/publish', $env, $owner);
        $this->assertGateBlocks($blocked, 'plan_limit_reached');

        // Existing data is never deleted (doc §5 / §12).
        $this->assertSame(5, DB::table('courses')
            ->where('environment_id', $env->id)
            ->where('status', 'published')
            ->count());
    }

    // ---------------------------------------------------------------------
    // Active learners limit (100 / 1000 / 5000)
    // ---------------------------------------------------------------------

    /** @test */
    public function free_101st_enrollment_is_blocked(): void
    {
        [$env, $owner] = $this->free();
        $this->seedActiveLearners($env, 100); // at the Free limit

        $blocked = $this->hit('POST', '/enrollments', $env, $owner, [
            'user_id' => 1, 'course_id' => 1,
        ]);
        $this->assertGateBlocks($blocked, 'plan_limit_reached');
    }

    /** @test */
    public function creator_enrollment_under_learner_limit_is_allowed(): void
    {
        [$env, $owner] = $this->creator();
        $this->seedActiveLearners($env, 5);

        $allowed = $this->hit('POST', '/enrollments', $env, $owner, [
            'user_id' => 1, 'course_id' => 1,
        ]);
        $this->assertGateAllows($allowed, 'plan_limit_reached');
    }

    // ---------------------------------------------------------------------
    // Admin/instructor seats (1 / 3 / 15)
    // ---------------------------------------------------------------------

    /** @test */
    public function free_second_admin_seat_blocked_creator_second_seat_allowed(): void
    {
        [$freeEnv, $freeOwner] = $this->free();
        $this->seedSeats($freeEnv, 1); // at Free limit of 1

        $blocked = $this->hit('POST', "/environments/{$freeEnv->id}/users", $freeEnv, $freeOwner, [
            'email' => 'new@example.com', 'role' => 'admin',
        ]);
        $this->assertGateBlocks($blocked, 'plan_limit_reached');

        [$creatorEnv, $creatorOwner] = $this->creator();
        $this->seedSeats($creatorEnv, 1); // under Creator's 3

        $allowed = $this->hit('POST', "/environments/{$creatorEnv->id}/users", $creatorEnv, $creatorOwner, [
            'email' => 'new@example.com', 'role' => 'admin',
        ]);
        $this->assertGateAllows($allowed, 'plan_limit_reached');
    }

    // ---------------------------------------------------------------------
    // Payment gateways (1 / multi)
    // ---------------------------------------------------------------------

    /** @test */
    public function free_second_gateway_blocked_creator_unlimited_allowed(): void
    {
        [$freeEnv, $freeOwner] = $this->free();
        $this->seedActiveGateways($freeEnv, 1); // at Free limit of 1

        $blocked = $this->hit('POST', '/payment-gateways', $freeEnv, $freeOwner, [
            'gateway_name' => 'Stripe', 'code' => 'stripe',
        ]);
        $this->assertGateBlocks($blocked, 'plan_limit_reached');

        [$creatorEnv, $creatorOwner] = $this->creator();
        $this->seedActiveGateways($creatorEnv, 1); // Creator = unlimited

        $allowed = $this->hit('POST', '/payment-gateways', $creatorEnv, $creatorOwner, [
            'gateway_name' => 'Stripe', 'code' => 'stripe',
        ]);
        $this->assertGateAllows($allowed, 'plan_limit_reached');
    }

    // ---------------------------------------------------------------------
    // Sales forms feature (C+)
    // ---------------------------------------------------------------------

    /** @test */
    public function sales_forms_write_blocked_free_allowed_creator(): void
    {
        [$freeEnv, $freeOwner] = $this->free();
        $blocked = $this->hit('POST', '/sales-forms', $freeEnv, $freeOwner, ['name' => 'F']);
        $this->assertGateBlocks($blocked, 'plan_feature_unavailable');

        [$creatorEnv, $creatorOwner] = $this->creator();
        $allowed = $this->hit('POST', '/sales-forms', $creatorEnv, $creatorOwner, ['name' => 'F']);
        $this->assertGateAllows($allowed, 'plan_feature_unavailable');
    }

    // ---------------------------------------------------------------------
    // Custom domain feature (W only)
    // ---------------------------------------------------------------------

    /** @test */
    public function custom_domain_blocked_free_and_creator_allowed_white_label(): void
    {
        [$freeEnv, $freeOwner] = $this->free();
        $this->assertGateBlocks(
            $this->hit('POST', '/domains/validate', $freeEnv, $freeOwner, ['domain' => 'x.com']),
            'plan_feature_unavailable'
        );

        [$creatorEnv, $creatorOwner] = $this->creator();
        $this->assertGateBlocks(
            $this->hit('POST', '/domains/validate', $creatorEnv, $creatorOwner, ['domain' => 'x.com']),
            'plan_feature_unavailable'
        );

        [$wlEnv, $wlOwner] = $this->whiteLabel();
        $this->assertGateAllows(
            $this->hit('POST', '/domains/validate', $wlEnv, $wlOwner, ['domain' => 'x.com']),
            'plan_feature_unavailable'
        );
    }

    // ---------------------------------------------------------------------
    // Data exports feature (C+)
    // ---------------------------------------------------------------------

    /** @test */
    public function submissions_export_blocked_free_allowed_creator(): void
    {
        [$freeEnv, $freeOwner] = $this->free();
        $this->assertGateBlocks(
            $this->hit('GET', '/sales-forms/1/submissions/export', $freeEnv, $freeOwner),
            'plan_feature_unavailable'
        );

        [$creatorEnv, $creatorOwner] = $this->creator();
        $this->assertGateAllows(
            $this->hit('GET', '/sales-forms/1/submissions/export', $creatorEnv, $creatorOwner),
            'plan_feature_unavailable'
        );
    }

    // ---------------------------------------------------------------------
    // API tokens feature (none / limited / full)
    // ---------------------------------------------------------------------

    /** @test */
    public function api_token_creation_blocked_free_allowed_creator_and_white_label(): void
    {
        [$freeEnv, $freeOwner] = $this->free();
        $this->assertGateBlocks(
            $this->hit('POST', '/tokens', $freeEnv, $freeOwner, ['name' => 't']),
            'plan_feature_unavailable'
        );

        [$creatorEnv, $creatorOwner] = $this->creator();
        $this->assertGateAllows(
            $this->hit('POST', '/tokens', $creatorEnv, $creatorOwner, ['name' => 't']),
            'plan_feature_unavailable'
        );

        [$wlEnv, $wlOwner] = $this->whiteLabel();
        $this->assertGateAllows(
            $this->hit('POST', '/tokens', $wlEnv, $wlOwner, ['name' => 't']),
            'plan_feature_unavailable'
        );
    }

    // ---------------------------------------------------------------------
    // Dark deploy
    // ---------------------------------------------------------------------

    /** @test */
    public function enforcement_disabled_lets_everything_through(): void
    {
        config(['licensing.enforcement_enabled' => false]);

        [$env, $owner] = $this->free();
        $this->seedPublishedCourses($env, 5); // way over the Free limit

        $allowed = $this->hit('POST', '/courses/999/publish', $env, $owner);
        $this->assertGateAllows($allowed, 'plan_limit_reached');

        $sf = $this->hit('POST', '/sales-forms', $env, $owner, ['name' => 'F']);
        $this->assertGateAllows($sf, 'plan_feature_unavailable');
    }

    // ---------------------------------------------------------------------
    // Grace / past_due (doc §12)
    // ---------------------------------------------------------------------

    /** @test */
    public function grace_blocks_premium_writes_but_billing_and_reads_pass(): void
    {
        [$env, $owner] = $this->creator(EnvironmentLicence::STATUS_GRACE);

        // Premium-feature WRITE is read-only-blocked during grace.
        $this->assertGateBlocks(
            $this->hit('POST', '/sales-forms', $env, $owner, ['name' => 'F']),
            'licence_in_grace'
        );

        // Billing / licence route is never gated — must remain reachable.
        $billing = $this->hit('GET', '/environment-licences/current', $env, $owner);
        $billing->assertStatus(200);
        $this->assertNotSame('licence_in_grace', $billing->json('error'));

        // A READ on a premium-feature route still passes in grace.
        $read = $this->hit('GET', '/finance/overview', $env, $owner);
        $this->assertGateAllows($read, 'licence_in_grace');
        $this->assertGateAllows($read, 'plan_feature_unavailable');
    }
}
