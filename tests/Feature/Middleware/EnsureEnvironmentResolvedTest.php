<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureEnvironmentResolved;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tenant routes used to run unscoped when no environment resolved: the global
 * scope simply did not apply. The guard turns that into a refusal, after a
 * log-only phase that surfaces routes which legitimately run without one.
 */
class EnsureEnvironmentResolvedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'environment.required'])
            ->get('/api/_guarded', fn () => response()->json(['ok' => true]));
    }

    private function bearer(User $user, array $abilities = []): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('t', $abilities)->plainTextToken];
    }

    public function test_enforce_mode_refuses_with_the_stable_code_when_nothing_resolved(): void
    {
        config(['tenancy.environment_guard' => 'enforce']);
        $user = User::factory()->create();

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'app.getkursa.space'] + $this->bearer($user))
            ->assertForbidden()
            ->assertJsonPath('code', EnsureEnvironmentResolved::CODE);
    }

    public function test_enforce_mode_passes_a_bound_request_on_the_shared_host(): void
    {
        config(['tenancy.environment_guard' => 'enforce']);
        $environment = Environment::factory()->create();
        $user = User::factory()->create();

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'app.getkursa.space']
            + $this->bearer($user, ['environment_id:'.$environment->id]))
            ->assertOk();
    }

    public function test_enforce_mode_passes_a_tenant_host_request(): void
    {
        config(['tenancy.environment_guard' => 'enforce']);
        Environment::factory()->create(['primary_domain' => 'acme.test']);
        $user = User::factory()->create();

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'acme.test'] + $this->bearer($user))
            ->assertOk();
    }

    #[DataProvider('platformRoleProvider')]
    public function test_platform_staff_pass_without_a_binding(string $role): void
    {
        config(['tenancy.environment_guard' => 'enforce']);
        $staff = User::factory()->create(['role' => $role]);

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'manager.getkursa.space'] + $this->bearer($staff))
            ->assertOk();
    }

    public static function platformRoleProvider(): array
    {
        return [
            'admin' => ['admin'],
            'super_admin' => ['super_admin'],
            'sales_agent' => ['sales_agent'],
        ];
    }

    public function test_log_mode_lets_the_request_through_and_logs(): void
    {
        config(['tenancy.environment_guard' => 'log']);
        Log::spy();
        $user = User::factory()->create();

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'app.getkursa.space'] + $this->bearer($user))
            ->assertOk();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => $message === 'tenancy.environment_required')
            ->once();
    }

    /**
     * A mistyped mode must refuse rather than silently reopen the tenant
     * routes: only the exact string 'log' passes an unresolved request.
     */
    public function test_a_mistyped_enforce_value_still_enforces(): void
    {
        config(['tenancy.environment_guard' => 'Enforce ']);
        $user = User::factory()->create();

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'app.getkursa.space'] + $this->bearer($user))
            ->assertForbidden()
            ->assertJsonPath('code', EnsureEnvironmentResolved::CODE);
    }

    public function test_an_unrecognised_mode_enforces_and_logs_the_misconfiguration(): void
    {
        config(['tenancy.environment_guard' => 'bogus']);
        Log::spy();
        $user = User::factory()->create();

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'app.getkursa.space'] + $this->bearer($user))
            ->assertForbidden()
            ->assertJsonPath('code', EnsureEnvironmentResolved::CODE);

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($message) => $message === 'tenancy.environment_guard_invalid')
            ->once();
    }

    /**
     * Every authenticated api/ route either carries the guard or is on the
     * explicit list of identity/membership/platform routes that run without a
     * binding.
     *
     * The walk stops at api/: the remaining auth:sanctum routes are Jetstream's
     * Blade surface, which is the platform's own UI rather than a tenant API
     * and, being vendor-declared, cannot carry the alias anyway. Those skipped
     * URIs are asserted exactly, so a future authenticated non-api tenant route
     * fails the walk instead of slipping past it.
     */
    public function test_every_authenticated_route_is_guarded_or_exempt(): void
    {
        $exemptUris = [
            'api/user', 'api/session/user', 'api/user/environments',
            'api/environments/{id}/join', 'api/environments/{id}/leave',
            'api/auth/academy-switch-token', 'api/session/logout', 'api/session/marketplace-token',
            'api/tokens', 'api/broadcasting/auth', 'api/environment-users/setup-account',
            'api/admin/sales/user', 'api/admin/sales/logout',
        ];
        $exemptPrefixes = ['api/admin/'];
        $knownNonApi = [
            'current-team', 'dashboard', 'team-invitations/{invitation}',
            'teams/create', 'teams/{team}', 'user/api-tokens', 'user/profile',
        ];
        $missing = [];
        $skipped = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            if (! in_array('auth:sanctum', $middleware, true)) {
                continue;
            }

            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/')) {
                $skipped[] = $uri;

                continue;
            }

            $exempt = in_array($uri, $exemptUris, true)
                || collect($exemptPrefixes)->contains(fn ($p) => str_starts_with($uri, $p));

            if (! $exempt && ! in_array('environment.required', $middleware, true)) {
                $missing[] = implode('|', $route->methods()).' '.$uri;
            }
        }

        $this->assertSame([], $missing, "auth:sanctum routes missing environment.required:\n".implode("\n", $missing));

        $skipped = array_values(array_unique($skipped));
        sort($skipped);

        $this->assertSame($knownNonApi, $skipped, 'the walk skipped an authenticated non-api route it does not know about');
    }
}
