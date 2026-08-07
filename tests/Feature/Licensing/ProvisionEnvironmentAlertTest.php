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
     * KURSA issues *.csl-brands.com subdomains, but the alert only recognised the
     * legacy .cfpcsl.com suffix, so every KURSA academy was labelled
     * "Custom Domain".
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

        $this->assertStringEndsWith('.csl-brands.com', $environment->primary_domain);
        $this->assertStringContainsString('Type: `Subdomain`', $captured);
        $this->assertStringNotContainsString('Custom Domain', $captured);
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
