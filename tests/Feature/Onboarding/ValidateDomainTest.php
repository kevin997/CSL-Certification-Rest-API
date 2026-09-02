<?php

namespace Tests\Feature\Onboarding;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression cover for the production 500 on POST /api/onboarding/validate-domain.
 *
 * Two independent defects produced it:
 *
 *  1. The route carried `licence.feature:custom_domain`. That gate is
 *     environment-scoped, but the route is an anonymous pre-signup availability
 *     check — no environment exists yet, so the gate could only ever fail open
 *     (CheckPlanFeature::handle()). It was dead weight on a public endpoint, and
 *     it was the sole reason this route touched the licensing middleware at all.
 *
 *  2. The deployed image had a `routes/api.php` that referenced the
 *     `licence.feature` alias against a `bootstrap/app.php` that never registered
 *     it, so the container tried to resolve "licence.feature" as a class name and
 *     threw BindingResolutionException -> 500. `test_every_route_middleware_alias_is_registered`
 *     below fails loudly on that skew instead of letting it reach production.
 */
class ValidateDomainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The reported production request: an anonymous call originating from the
     * marketing site, whose host is deliberately NOT a registered environment.
     */
    public function test_validate_domain_succeeds_for_an_origin_with_no_environment(): void
    {
        $this->assertNull(Environment::findByDomain('www.getkursa.space'));

        $response = $this->postJson('/api/onboarding/validate-domain', [
            'domain' => 'maclassedechant.csl-brands.com',
            'type' => 'subdomain',
        ], [
            'Origin' => 'https://www.getkursa.space',
            'Referer' => 'https://www.getkursa.space/',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'available' => true,
            ]);
    }

    /**
     * Enforcement being switched on must not change the public route's behaviour,
     * because there is no tenant to scope an entitlement against.
     */
    public function test_validate_domain_is_not_licence_gated_when_enforcement_is_enabled(): void
    {
        config(['licensing.enforcement_enabled' => true]);

        $response = $this->postJson('/api/onboarding/validate-domain', [
            'domain' => 'brand-new-academy.csl-brands.com',
            'type' => 'subdomain',
        ], [
            'Origin' => 'https://www.getkursa.space',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('error', $response->json());
    }

    public function test_validate_domain_reports_a_taken_subdomain_with_suggestions(): void
    {
        $owner = User::factory()->create();

        Environment::create([
            'name' => 'Taken Academy',
            'primary_domain' => 'maclassedechant.getkursa.space',
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/onboarding/validate-domain', [
            'domain' => 'maclassedechant',
            'type' => 'subdomain',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'available' => false,
            ]);

        $this->assertNotEmpty($response->json('suggestions'));
    }

    public function test_validate_domain_rejects_a_malformed_subdomain(): void
    {
        $this->postJson('/api/onboarding/validate-domain', [
            'domain' => 'not-a-csl-subdomain.example.com',
            'type' => 'subdomain',
        ])->assertStatus(422);
    }

    /**
     * Guards the actual production failure mode: a route referencing a middleware
     * alias that the application never registered. Laravel only surfaces that at
     * request time, as an opaque 500.
     */
    public function test_every_route_middleware_alias_is_registered(): void
    {
        $aliases = app('router')->getMiddleware();
        $groups = app('router')->getMiddlewareGroups();
        $unresolved = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware)) {
                    continue;
                }

                $name = explode(':', $middleware, 2)[0];

                if (isset($aliases[$name]) || isset($groups[$name]) || class_exists($name)) {
                    continue;
                }

                $unresolved[$name][] = $route->uri();
            }
        }

        $this->assertSame([], $unresolved, sprintf(
            'Unregistered route middleware aliases (register them in bootstrap/app.php): %s',
            json_encode($unresolved)
        ));
    }
}
