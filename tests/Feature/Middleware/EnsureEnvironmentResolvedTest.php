<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureEnvironmentResolved;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
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

    public function test_platform_staff_pass_without_a_binding(): void
    {
        config(['tenancy.environment_guard' => 'enforce']);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'manager.getkursa.space'] + $this->bearer($admin))
            ->assertOk();
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
     * Every authenticated api/ route either carries the guard or is on the
     * explicit list of identity/membership/platform routes that run without a
     * binding.
     *
     * The walk stops at api/: the remaining auth:sanctum routes are Jetstream's
     * Blade surface (user/profile, teams/*, current-team, dashboard), which is
     * the platform's own UI rather than a tenant API and, being vendor-declared,
     * cannot carry the alias anyway.
     */
    public function test_every_authenticated_route_is_guarded_or_exempt(): void
    {
        $exemptUris = [
            'api/user', 'api/session/user', 'api/user/environments', 'api/environments/user',
            'api/environments/{id}/join', 'api/environments/{id}/leave',
            'api/auth/academy-switch-token', 'api/session/logout', 'api/session/marketplace-token',
            'api/tokens', 'api/logout', 'api/broadcasting/auth', 'api/environment-users/setup-account',
            'api/admin/sales/user', 'api/admin/sales/logout', 'api/_guarded',
        ];
        $exemptPrefixes = ['api/admin/'];
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            if (! in_array('auth:sanctum', $middleware, true)) {
                continue;
            }

            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            $exempt = in_array($uri, $exemptUris, true)
                || collect($exemptPrefixes)->contains(fn ($p) => str_starts_with($uri, $p));

            if (! $exempt && ! in_array('environment.required', $middleware, true)) {
                $missing[] = implode('|', $route->methods()).' '.$uri;
            }
        }

        $this->assertSame([], $missing, "auth:sanctum routes missing environment.required:\n".implode("\n", $missing));
    }
}
