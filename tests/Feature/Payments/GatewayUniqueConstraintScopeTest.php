<?php

namespace Tests\Feature\Payments;

use App\Models\Environment;
use App\Models\PaymentGatewaySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The model's own guards must not depend on whose session is open.
 *
 * validateUniqueConstraints and the is_default hook both queried through
 * self::query(), which carries EnvironmentScope. That scope filters on
 * session('current_environment_id'), so whenever the row being saved belongs to
 * a DIFFERENT environment than the session names -- exactly the centralized
 * case, where an admin on the tenant edits the provider's gateways -- the two
 * predicates are mutually exclusive and the guard silently matches nothing.
 *
 * A guard that quietly passes is worse than none: it permits the duplicate code
 * the database will then reject, and leaves two rows flagged is_default.
 */
class GatewayUniqueConstraintScopeTest extends TestCase
{
    use RefreshDatabase;

    private function gateway(int $environmentId, string $code, bool $isDefault = false): PaymentGatewaySetting
    {
        return PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $environmentId,
            'gateway_name' => 'TaraMoney',
            'code' => $code,
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => $isDefault,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['api_key' => 'k'],
        ]);
    }

    public function test_a_duplicate_code_is_rejected_even_when_the_session_names_another_environment(): void
    {
        $provider = Environment::factory()->create(['is_active' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);

        $this->gateway($provider->id, 'taramoney');

        // An admin working on the tenant, editing the provider's gateways.
        session(['current_environment_id' => $tenant->id]);

        $this->expectException(ValidationException::class);
        $this->gateway($provider->id, 'taramoney');
    }

    public function test_the_same_code_in_a_different_environment_is_still_allowed(): void
    {
        $a = Environment::factory()->create(['is_active' => true]);
        $b = Environment::factory()->create(['is_active' => true]);

        $this->gateway($a->id, 'taramoney');
        $second = $this->gateway($b->id, 'taramoney');

        $this->assertTrue($second->exists, 'codes are unique per environment, not globally');
    }

    public function test_setting_a_default_clears_the_previous_one_in_that_environment(): void
    {
        $provider = Environment::factory()->create(['is_active' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);

        $previous = $this->gateway($provider->id, 'taramoney', true);

        session(['current_environment_id' => $tenant->id]);

        $next = $this->gateway($provider->id, 'lygos');
        $next->is_default = true;
        $next->save();

        $this->assertFalse(
            $previous->fresh()->is_default,
            'an environment must not be left with two default gateways'
        );
        $this->assertTrue($next->fresh()->is_default);
    }

    public function test_setting_a_default_does_not_touch_another_environments_default(): void
    {
        $a = Environment::factory()->create(['is_active' => true]);
        $b = Environment::factory()->create(['is_active' => true]);

        $theirs = $this->gateway($b->id, 'taramoney', true);
        $mine = $this->gateway($a->id, 'lygos');

        $mine->is_default = true;
        $mine->save();

        $this->assertTrue($theirs->fresh()->is_default, 'defaults are per environment');
    }
}
