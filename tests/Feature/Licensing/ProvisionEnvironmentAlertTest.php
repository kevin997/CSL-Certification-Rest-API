<?php

namespace Tests\Feature\Licensing;

use App\Services\Licensing\LicenceService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * The three legacy onboarding controllers (Standalone/Supported/Demo) each fire
 * EnvironmentCreatedNotification->toTelegram() by hand right after creating the
 * environment. KURSA onboarding — POST /onboarding/free, /onboarding/trial, and
 * paid checkout completion — instead funnels through
 * LicenceService::provisionEnvironmentFromPayload(), which never did, so those
 * environments were provisioned with no ops alert at all.
 *
 * Note TelegramService::sendMessage() talks to api.telegram.org over raw cURL,
 * so Http::fake() cannot intercept it — these tests bind a double into the
 * container instead, which also keeps the suite off the network.
 */
class ProvisionEnvironmentAlertTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Semba Ghislaine',
            'email' => 'owner-'.uniqid().'@example.com',
            'environment_name' => 'Ma Classe De Chant',
            'domain_type' => 'subdomain',
            'domain' => 'ma-classe-'.uniqid(),
        ], $overrides);
    }

    private function fakeTelegram(): Mockery\MockInterface
    {
        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('getChatId')->andReturn('-1001836815830');
        $telegram->shouldReceive('escapeMarkdownV2')->andReturnUsing(fn (string $text): string => $text);

        $this->app->instance(TelegramService::class, $telegram);

        return $telegram;
    }

    public function test_provisioning_an_environment_sends_the_telegram_alert(): void
    {
        Mail::fake();
        $telegram = $this->fakeTelegram();

        $captured = null;
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function ($chatId, $message) use (&$captured) {
                $captured = $message;

                return true;
            });

        $environment = app(LicenceService::class)->provisionEnvironmentFromPayload($this->payload());

        $this->assertNotNull($captured, 'no Telegram alert was sent for a provisioned environment');
        $this->assertStringContainsString('New Environment Created', $captured);
        $this->assertStringContainsString($environment->primary_domain, $captured);
        $this->assertStringContainsString('Ma Classe De Chant', $captured);
    }

    /**
     * KURSA issues *.getkursa.space subdomains (legacy environments live under
     * .csl-brands.com or .cfpcsl.com), and the alert must recognise all of them
     * so no KURSA academy is mislabelled "Custom Domain".
     */
    public function test_a_csl_brands_subdomain_is_labelled_a_subdomain(): void
    {
        Mail::fake();
        $telegram = $this->fakeTelegram();

        $captured = null;
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function ($chatId, $message) use (&$captured) {
                $captured = $message;

                return true;
            });

        $environment = app(LicenceService::class)->provisionEnvironmentFromPayload($this->payload());

        $this->assertStringEndsWith('.getkursa.space', $environment->primary_domain);
        $this->assertStringContainsString('Type: `Subdomain`', $captured);
        $this->assertStringNotContainsString('Custom Domain', $captured);
    }

    /**
     * Admins need to be able to recover an account whose owner never opened the
     * emailed link, so the alert carries the same single-use set-password URL.
     */
    public function test_the_alert_carries_the_password_set_link(): void
    {
        Mail::fake();
        $telegram = $this->fakeTelegram();

        $captured = null;
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function ($chatId, $message) use (&$captured) {
                $captured = $message;

                return true;
            });

        $environment = app(LicenceService::class)->provisionEnvironmentFromPayload(
            $this->payload(['email' => 'ghislaine-'.uniqid().'@example.com'])
        );

        $this->assertStringContainsString('Set', $captured);
        // A freshly provisioned academy has no verified domain yet, so its
        // password-set link points at the shared host and carries the id.
        $this->assertStringContainsString(
            'https://app.getkursa.space/auth/reset-password',
            $captured
        );
        $this->assertStringContainsString('environment_id='.$environment->id, $captured);

        // The token in the alert must be the one that actually works.
        preg_match('/token=([A-Za-z0-9]+)/', $captured, $m);
        $this->assertNotEmpty($m[1] ?? null, 'no token in the alert link');
        $this->assertDatabaseHas('password_reset_metadata', ['token' => $m[1]]);
    }

    /**
     * The alert is best-effort: provisioning has already created the environment
     * and emailed the owner by the time it fires, so a Telegram outage must not
     * surface as a failed onboarding.
     */
    public function test_a_telegram_failure_does_not_break_provisioning(): void
    {
        Mail::fake();
        $telegram = $this->fakeTelegram();
        $telegram->shouldReceive('sendMessage')->andThrow(new \RuntimeException('telegram is down'));

        $environment = app(LicenceService::class)->provisionEnvironmentFromPayload($this->payload());

        $this->assertNotNull($environment->id);
        $this->assertDatabaseHas('environments', ['id' => $environment->id]);
    }
}
