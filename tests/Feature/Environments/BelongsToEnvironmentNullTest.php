<?php

namespace Tests\Feature\Environments;

use App\Models\Branding;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An explicit null environment means "platform-scoped", not "please guess".
 *
 * BelongsToEnvironment's creating hook tested `! $model->environment_id`, which
 * cannot tell an omitted attribute from one deliberately set to null. Rows meant
 * to belong to the platform were therefore silently reassigned to whichever
 * environment the request happened to resolve -- and for PaymentGatewaySetting
 * that made platform gateways uncreatable through the model at all, while the
 * code that reads them (PlatformPaymentGatewayResolver) queries precisely
 * `whereNull('environment_id')`.
 */
class BelongsToEnvironmentNullTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_omitted_environment_is_still_detected(): void
    {
        $environment = Environment::factory()->create(['is_active' => true]);
        session(['current_environment_id' => $environment->id]);

        $branding = Branding::create(['company_name' => 'Detected', 'user_id' => User::factory()->create()->id]);

        $this->assertSame(
            $environment->id,
            $branding->fresh()->environment_id,
            'omitting the attribute must keep the existing auto-detection'
        );
    }

    public function test_an_explicit_null_environment_survives(): void
    {
        $environment = Environment::factory()->create(['is_active' => true]);
        session(['current_environment_id' => $environment->id]);

        $branding = Branding::create([
            'company_name' => 'Platform',
            'user_id' => User::factory()->create()->id,
            'environment_id' => null,
        ]);

        $this->assertNull(
            $branding->fresh()->environment_id,
            'an explicit null means platform-scoped and must not be overwritten'
        );
    }

    public function test_an_explicit_environment_is_never_overwritten(): void
    {
        $mine = Environment::factory()->create(['is_active' => true]);
        $other = Environment::factory()->create(['is_active' => true]);
        session(['current_environment_id' => $other->id]);

        $branding = Branding::create([
            'company_name' => 'Explicit',
            'user_id' => User::factory()->create()->id,
            'environment_id' => $mine->id,
        ]);

        $this->assertSame($mine->id, $branding->fresh()->environment_id);
    }
}
