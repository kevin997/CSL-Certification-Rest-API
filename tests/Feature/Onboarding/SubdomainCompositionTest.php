<?php

namespace Tests\Feature\Onboarding;

use App\Models\Environment;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SubdomainCompositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();
        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('getChatId')->andReturn('-100');
        $telegram->shouldReceive('escapeMarkdownV2')->andReturnUsing(fn (string $t): string => $t);
        $telegram->shouldReceive('sendMessage')->andReturn(true);
        $this->app->instance(TelegramService::class, $telegram);
    }

    public function test_free_onboarding_composes_the_subdomain_under_the_configured_base(): void
    {
        $response = $this->postJson('/api/onboarding/free', [
            'name' => 'Owner', 'email' => 'owner@example.com', 'environment_name' => 'Academy',
            'domain_type' => 'subdomain', 'domain' => 'Acme',
        ])->assertCreated();

        $this->assertSame('acme.getkursa.space', $response->json('domain'));
        $this->assertDatabaseHas('environments', ['primary_domain' => 'acme.getkursa.space']);
    }

    public function test_free_onboarding_rejects_a_reserved_or_malformed_label(): void
    {
        foreach (['app', 'a b'] as $label) {
            $this->postJson('/api/onboarding/free', [
                'name' => 'Owner', 'email' => $label.'@example.com', 'environment_name' => 'Academy',
                'domain_type' => 'subdomain', 'domain' => $label,
            ])->assertStatus(422);
        }
    }

    public function test_validate_domain_accepts_a_bare_label_and_returns_the_composed_host(): void
    {
        $this->postJson('/api/onboarding/validate-domain', ['domain' => 'brand-new', 'type' => 'subdomain'])
            ->assertOk()
            ->assertJson(['success' => true, 'available' => true, 'domain' => 'brand-new.getkursa.space']);
    }

    public function test_validate_domain_accepts_a_legacy_host_and_checks_the_composed_host(): void
    {
        Environment::factory()->create(['primary_domain' => 'taken.getkursa.space']);

        $response = $this->postJson('/api/onboarding/validate-domain', ['domain' => 'taken.csl-brands.com', 'type' => 'subdomain'])
            ->assertOk()
            ->assertJson(['success' => true, 'available' => false, 'domain' => 'taken.getkursa.space']);

        $this->assertStringEndsWith('.getkursa.space', $response->json('suggestions.0'));
    }

    public function test_validate_domain_rejects_reserved_labels(): void
    {
        $this->postJson('/api/onboarding/validate-domain', ['domain' => 'www', 'type' => 'subdomain'])
            ->assertStatus(422);
    }
}
