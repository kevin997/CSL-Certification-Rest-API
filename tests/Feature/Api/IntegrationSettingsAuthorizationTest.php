<?php

namespace Tests\Feature\Api;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\MarketingAutomation;
use App\Models\ThirdPartyService;
use App\Models\User;
use Database\Seeders\ThirdPartyServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegrationSettingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['licensing.enforcement_enabled' => false]);
    }

    private function service(?int $environmentId, array $attributes = []): ThirdPartyService
    {
        $service = new ThirdPartyService(array_merge([
            'name' => 'WhatsApp',
            'service_type' => 'whatsapp',
            'base_url' => 'https://service.example.test',
            'api_key' => 'api-key-secret',
            'api_secret' => 'api-secret-value',
            'bearer_token' => 'bearer-token-value',
            'username' => 'service-user',
            'password' => 'service-password',
            'is_active' => true,
            'config' => ['type' => 'group', 'value' => 'https://chat.whatsapp.com/example'],
        ], $attributes));
        $service->environment_id = $environmentId;
        $service->save();

        return $service;
    }

    private function ownerOf(Environment $environment): User
    {
        return User::query()->findOrFail($environment->owner_id);
    }

    private function actingIn(User $user, Environment $environment): self
    {
        return $this->actingAs($user)->withHeader('X-Frontend-Domain', $environment->primary_domain);
    }

    public function test_learner_cannot_read_or_modify_integration_settings(): void
    {
        $environment = Environment::factory()->create();
        $learner = User::factory()->create(['role' => 'learner']);
        EnvironmentUser::create([
            'environment_id' => $environment->id,
            'user_id' => $learner->id,
            'role' => 'learner',
        ]);

        $this->actingIn($learner, $environment)
            ->getJson('/api/third-party-services')
            ->assertForbidden();

        $this->actingIn($learner, $environment)
            ->getJson('/api/marketing-automations')
            ->assertForbidden();

        $this->actingIn($learner, $environment)
            ->putJson('/api/marketing-automations/form_submitted', [
                'enabled' => true,
                'channels' => ['email'],
                'recipient' => 'customer',
            ])
            ->assertForbidden();
    }

    public function test_a_teacher_who_is_only_a_learner_in_this_environment_cannot_manage_integrations(): void
    {
        $environmentOwner = User::factory()->create(['role' => 'company_teacher']);
        $environment = Environment::factory()->create(['owner_id' => $environmentOwner->id]);
        $teacher = User::factory()->create(['role' => 'company_teacher']);
        Environment::factory()->create(['owner_id' => $teacher->id]);
        EnvironmentUser::create([
            'environment_id' => $environment->id,
            'user_id' => $teacher->id,
            'role' => 'learner',
        ]);

        $this->actingIn($teacher, $environment)
            ->getJson('/api/third-party-services')
            ->assertForbidden();

        $this->actingIn($teacher, $environment)
            ->getJson('/api/marketing-automations')
            ->assertForbidden();
    }

    public function test_environment_owner_sees_only_their_services_and_no_secrets(): void
    {
        $environment = Environment::factory()->create();
        $owner = $this->ownerOf($environment);
        $tenantService = $this->service($environment->id);
        $platformService = $this->service(null, ['service_type' => 'certificate_generation']);

        $this->actingIn($owner, $environment)
            ->getJson('/api/third-party-services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tenantService->id)
            ->assertJsonMissing(['api_key' => 'api-key-secret'])
            ->assertJsonMissing(['api_secret' => 'api-secret-value'])
            ->assertJsonMissing(['bearer_token' => 'bearer-token-value'])
            ->assertJsonMissing(['username' => 'service-user'])
            ->assertJsonMissing(['password' => 'service-password']);

        $this->actingIn($owner, $environment)
            ->getJson("/api/third-party-services/{$platformService->id}")
            ->assertNotFound();
    }

    public function test_platform_administrator_can_manage_only_platform_scoped_services(): void
    {
        $administrator = User::factory()->create(['role' => 'super_admin']);
        $platformService = $this->service(null, ['service_type' => 'certificate_generation']);
        $tenantService = $this->service(Environment::factory()->create()->id);

        $this->actingAs($administrator)
            ->getJson('/api/third-party-services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $platformService->id)
            ->assertJsonMissing(['password' => 'service-password']);

        $this->actingAs($administrator)
            ->getJson("/api/third-party-services/{$tenantService->id}")
            ->assertNotFound();

        $this->actingAs($administrator)
            ->putJson("/api/third-party-services/{$platformService->id}", ['password' => 'rotated-password'])
            ->assertOk()
            ->assertJsonMissing(['password' => 'rotated-password']);

        $this->assertSame('rotated-password', $platformService->fresh()->password);
    }

    public function test_environment_owner_creates_a_whatsapp_service_in_the_resolved_environment(): void
    {
        $environment = Environment::factory()->create();

        $this->actingIn($this->ownerOf($environment), $environment)
            ->postJson('/api/third-party-services', [
                'name' => 'Academy WhatsApp',
                'service_type' => 'whatsapp',
                'api_key' => 'do-not-return-this',
                'config' => ['type' => 'group', 'value' => 'https://chat.whatsapp.com/academy'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.environment_id', $environment->id)
            ->assertJsonMissing(['api_key' => 'do-not-return-this']);

        $this->getJson('/api/integrations/whatsapp/config', [
            'X-Frontend-Domain' => $environment->primary_domain,
        ])
            ->assertOk()
            ->assertJsonPath('data.value', 'https://chat.whatsapp.com/academy');
    }

    public function test_create_rejects_client_selected_environment_id(): void
    {
        $environment = Environment::factory()->create();
        $otherEnvironment = Environment::factory()->create();

        $this->actingIn($this->ownerOf($environment), $environment)
            ->postJson('/api/third-party-services', [
                'name' => 'Injected service',
                'service_type' => 'whatsapp',
                'environment_id' => $otherEnvironment->id,
                'config' => ['type' => 'group', 'value' => 'https://chat.whatsapp.com/injected'],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('third_party_services', ['name' => 'Injected service']);
    }

    public function test_environment_owner_cannot_update_another_environments_service(): void
    {
        $environment = Environment::factory()->create();
        $otherEnvironment = Environment::factory()->create();
        $otherService = $this->service($otherEnvironment->id);

        $this->actingIn($this->ownerOf($environment), $environment)
            ->putJson("/api/third-party-services/{$otherService->id}", [
                'config' => ['type' => 'group', 'value' => 'https://chat.whatsapp.com/injected'],
            ])
            ->assertNotFound();

        $this->assertSame('https://chat.whatsapp.com/example', data_get($otherService->fresh()->config, 'value'));
    }

    public function test_service_credentials_are_encrypted_in_storage(): void
    {
        $service = $this->service(null);
        $storedApiKey = DB::table('third_party_services')->where('id', $service->id)->value('api_key');
        $storedPassword = DB::table('third_party_services')->where('id', $service->id)->value('password');

        $this->assertNotSame('api-key-secret', $storedApiKey);
        $this->assertNotSame('service-password', $storedPassword);
        $this->assertSame('api-key-secret', $service->fresh()->api_key);
        $this->assertSame('service-password', $service->fresh()->password);
    }

    public function test_certificate_service_seeder_does_not_create_default_credentials(): void
    {
        config([
            'services.certificate_generation_seed.api_key' => null,
            'services.certificate_generation_seed.bearer_token' => null,
            'services.certificate_generation_seed.username' => null,
            'services.certificate_generation_seed.password' => null,
        ]);

        $this->seed(ThirdPartyServiceSeeder::class);

        $service = ThirdPartyService::withoutGlobalScopes()
            ->whereNull('environment_id')
            ->where('service_type', 'certificate_generation')
            ->firstOrFail();

        $this->assertNull($service->password);
        $this->assertFalse($service->is_active);
    }

    public function test_migration_encrypts_existing_plaintext_service_credentials(): void
    {
        $migration = require database_path('migrations/2026_09_27_095702_secure_third_party_service_credentials.php');
        $migration->down();

        $serviceId = DB::table('third_party_services')->insertGetId([
            'name' => 'Legacy certificate service',
            'base_url' => 'https://certificate.example.test',
            'api_key' => 'legacy-api-key',
            'api_secret' => 'legacy-api-secret',
            'bearer_token' => 'legacy-bearer-token',
            'username' => 'legacy-user',
            'password' => 'legacy-password',
            'is_active' => true,
            'service_type' => 'certificate_generation',
            'config' => json_encode(['verify_ssl' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $service = ThirdPartyService::withoutGlobalScopes()->findOrFail($serviceId);
        $storedApiKey = DB::table('third_party_services')->where('id', $serviceId)->value('api_key');

        $this->assertSame('legacy-api-key', $service->api_key);
        $this->assertSame('legacy-password', $service->password);
        $this->assertNotSame('legacy-api-key', $storedApiKey);
    }

    public function test_marketing_automation_upsert_is_scoped_to_the_resolved_environment(): void
    {
        $environment = Environment::factory()->create();
        $otherEnvironment = Environment::factory()->create();
        $otherAutomation = MarketingAutomation::withoutGlobalScopes()->create([
            'environment_id' => $otherEnvironment->id,
            'trigger' => MarketingAutomation::TRIGGER_FORM_SUBMITTED,
            'enabled' => true,
            'channels' => ['email'],
            'recipient' => MarketingAutomation::RECIPIENT_INSTRUCTOR,
            'email_subject' => 'Existing subject',
            'email_body' => 'Existing body',
        ]);

        $this->actingIn($this->ownerOf($environment), $environment)
            ->putJson('/api/marketing-automations/form_submitted', [
                'enabled' => true,
                'channels' => ['whatsapp'],
                'recipient' => 'customer',
                'email_subject' => 'Tenant A subject',
                'email_body' => 'Tenant A body',
                'environment_id' => $otherEnvironment->id,
            ])
            ->assertUnprocessable();

        $this->actingIn($this->ownerOf($environment), $environment)
            ->putJson('/api/marketing-automations/form_submitted', [
                'enabled' => true,
                'channels' => ['whatsapp'],
                'recipient' => 'customer',
                'email_subject' => 'Tenant A subject',
                'email_body' => 'Tenant A body',
            ])
            ->assertOk()
            ->assertJsonPath('data.environment_id', $environment->id)
            ->assertJsonPath('data.email_subject', 'Tenant A subject');

        $this->actingIn($this->ownerOf($environment), $environment)
            ->getJson('/api/marketing-automations')
            ->assertOk()
            ->assertJsonFragment(['email_subject' => 'Tenant A subject'])
            ->assertJsonMissing(['email_subject' => 'Existing subject']);

        $this->assertSame('Existing subject', $otherAutomation->fresh()->email_subject);
        $this->assertSame(2, MarketingAutomation::withoutGlobalScopes()->count());
    }
}
