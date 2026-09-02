<?php

namespace Tests\Feature\Onboarding;

use App\Models\Environment;
use App\Models\LicenceCheckout;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * After onboarding the owner is taken to the shared host already signed in:
 * provisioning mints a one-time switch token and returns the switch URL. Paid
 * flows mint it on demand from a dedicated POST, never from the polled status.
 */
class OnboardingSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('getChatId')->andReturn('-100');
        $telegram->shouldReceive('escapeMarkdownV2')->andReturnUsing(fn (string $text): string => $text);
        $telegram->shouldReceive('sendMessage')->andReturn(true);
        $this->app->instance(TelegramService::class, $telegram);
    }

    private function payload(): array
    {
        return [
            'name' => 'Semba Ghislaine',
            'email' => 'owner-'.uniqid().'@example.com',
            'environment_name' => 'Ma Classe De Chant',
            'domain_type' => 'subdomain',
            'domain' => 'ma-classe-'.substr(uniqid(), -6),
        ];
    }

    public function test_free_onboarding_returns_a_usable_one_time_sign_in_url_on_the_shared_host(): void
    {
        $response = $this->postJson('/api/onboarding/free', $this->payload(), ['Origin' => 'https://www.getkursa.space'])
            ->assertCreated();

        $redirect = $response->json('redirect_url');
        $environmentId = $response->json('environment_id');

        $this->assertStringStartsWith('https://app.getkursa.space/auth/switch?token=', $redirect);
        $this->assertStringContainsString('environment_id='.$environmentId, $redirect);

        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);

        $exchange = $this->postJson('/api/auth/validate-switch-token', ['token' => $query['token']])
            ->assertOk()
            ->assertJsonPath('environment_id', $environmentId)
            ->assertJsonPath('is_account_setup', false);

        $this->assertNotEmpty($exchange->json('token'));
        $this->postJson('/api/auth/validate-switch-token', ['token' => $query['token']])->assertStatus(401);
    }

    public function test_trial_onboarding_also_returns_the_sign_in_url(): void
    {
        $this->postJson('/api/onboarding/trial', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['environment_id', 'domain', 'redirect_url', 'trial_ends_at']);
    }

    public function test_the_sign_in_link_is_refused_until_the_checkout_is_paid_and_provisioned(): void
    {
        $checkout = LicenceCheckout::create([
            'plan_type' => 'creator_monthly',
            'quoted_amount' => 20,
            'quoted_currency' => 'USD',
            'status' => LicenceCheckout::STATUS_PENDING_PAYMENT,
        ]);

        $this->postJson("/api/licence-checkouts/{$checkout->uuid}/sign-in-link")
            ->assertStatus(409)
            ->assertJsonPath('code', 'checkout_not_ready');

        $this->postJson('/api/licence-checkouts/00000000-0000-0000-0000-000000000000/sign-in-link')
            ->assertNotFound();
    }

    public function test_the_sign_in_link_mints_a_token_for_the_owner_once_paid(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        $checkout = LicenceCheckout::create([
            'plan_type' => 'creator_monthly',
            'quoted_amount' => 20,
            'quoted_currency' => 'USD',
            'status' => LicenceCheckout::STATUS_PAID,
            'environment_id' => $environment->id,
        ]);

        $redirect = $this->postJson("/api/licence-checkouts/{$checkout->uuid}/sign-in-link")
            ->assertOk()
            ->json('redirect_url');

        $this->assertStringStartsWith('https://app.getkursa.space/auth/switch?token=', $redirect);

        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
        $this->postJson('/api/auth/validate-switch-token', ['token' => $query['token']])
            ->assertOk()
            ->assertJsonPath('environment_id', $environment->id)
            ->assertJsonPath('user.id', $owner->id);
    }
}
