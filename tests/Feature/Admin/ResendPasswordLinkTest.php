<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendWhatsAppNotification;
use App\Mail\EnvironmentResetPasswordMail;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use App\Services\TelegramService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Admins had no way to recover an owner who never received the onboarding
 * password-set mail: password_reset_tokens stores a bcrypt hash, so the original
 * link cannot be reconstructed.
 */
class ResendPasswordLinkTest extends TestCase
{
    use RefreshDatabase;

    private function environmentWithOwner(array $ownerAttributes = []): array
    {
        $owner = User::factory()->create(array_merge([
            'whatsapp_number' => '677123456',
        ], $ownerAttributes));

        $environment = Environment::create([
            'name' => 'Ma Classe De Chant',
            'primary_domain' => 'maclasse-'.uniqid().'.csl-brands.com',
            'owner_id' => $owner->id,
            'is_active' => true,
            'country_code' => 'CM',
        ]);

        return [$environment, $owner];
    }

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * Telegram is in the default channel set, and the real service curls
     * api.telegram.org — so every test that does not restrict channels must
     * swap it out or the suite makes live network calls.
     */
    private function fakeTelegram(bool $succeeds = true): void
    {
        $fake = \Mockery::mock(TelegramService::class);
        $fake->shouldReceive('getChatId')->andReturn('-100123456789');
        $fake->shouldReceive('escapeMarkdownV2')->andReturnUsing(fn ($t) => $t);
        $fake->shouldReceive('sendMessage')->andReturn($succeeds);

        $this->instance(TelegramService::class, $fake);
    }

    public function test_a_super_admin_can_resend_the_password_set_link(): void
    {
        $this->fakeTelegram();
        Mail::fake();
        Queue::fake();
        $this->actingAsSuperAdmin();
        [$environment, $owner] = $this->environmentWithOwner();

        $response = $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.owner_email', $owner->email);

        $url = $response->json('data.password_set_url');
        $this->assertStringStartsWith("https://{$environment->primary_domain}/auth/reset-password", $url);

        Mail::assertQueued(EnvironmentResetPasswordMail::class);
        Queue::assertPushed(SendWhatsAppNotification::class);
        $this->assertContains('telegram', $response->json('data.delivered'));
    }

    /**
     * The returned link must actually work — the token in the URL has to verify
     * against the bcrypt hash the reset flow checks.
     */
    public function test_the_issued_token_verifies_against_the_stored_hash(): void
    {
        $this->fakeTelegram();
        Mail::fake();
        Queue::fake();
        $this->actingAsSuperAdmin();
        [$environment, $owner] = $this->environmentWithOwner();

        $url = $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link")
            ->json('data.password_set_url');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $stored = DB::table('password_reset_tokens')->where('email', $owner->email)->value('token');

        $this->assertTrue(Hash::check($query['token'], $stored));
        $this->assertDatabaseHas('password_reset_metadata', ['token' => $query['token']]);
    }

    public function test_resending_invalidates_the_previous_link(): void
    {
        $this->fakeTelegram();
        Mail::fake();
        Queue::fake();
        $this->actingAsSuperAdmin();
        [$environment, $owner] = $this->environmentWithOwner();

        $first = $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link")
            ->json('data.password_set_url');
        $second = $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link")
            ->json('data.password_set_url');

        parse_str(parse_url($first, PHP_URL_QUERY), $firstQuery);
        parse_str(parse_url($second, PHP_URL_QUERY), $secondQuery);

        $this->assertNotSame($firstQuery['token'], $secondQuery['token']);

        $stored = DB::table('password_reset_tokens')->where('email', $owner->email)->value('token');
        $this->assertFalse(Hash::check($firstQuery['token'], $stored), 'the superseded link must stop working');
        $this->assertTrue(Hash::check($secondQuery['token'], $stored));
    }

    public function test_channels_can_be_restricted_to_email_only(): void
    {
        // Deliberately no fakeTelegram(): restricting channels must mean the
        // Telegram path is never entered, so a real send would blow up here.
        Mail::fake();
        Queue::fake();
        $this->actingAsSuperAdmin();
        [$environment] = $this->environmentWithOwner();

        $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link", [
            'channels' => ['email'],
        ])->assertOk()->assertJsonPath('data.delivered', ['email']);

        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }

    public function test_an_owner_without_a_whatsapp_number_still_gets_the_email(): void
    {
        Mail::fake();
        Queue::fake();
        $this->actingAsSuperAdmin();
        [$environment] = $this->environmentWithOwner(['whatsapp_number' => null]);

        $response = $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link", [
            'channels' => ['email', 'whatsapp'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.delivered', ['email'])
            ->assertJsonPath('data.failed', ['whatsapp']);

        Mail::assertQueued(EnvironmentResetPasswordMail::class);
    }

    public function test_telegram_is_reported_as_failed_when_the_send_does_not_go_through(): void
    {
        Mail::fake();
        Queue::fake();
        $this->fakeTelegram(succeeds: false);
        $this->actingAsSuperAdmin();
        [$environment] = $this->environmentWithOwner();

        $response = $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link", [
            'channels' => ['telegram'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.delivered', [])
            ->assertJsonPath('data.failed', ['telegram']);
    }

    public function test_an_unknown_channel_is_rejected(): void
    {
        $this->actingAsSuperAdmin();
        [$environment] = $this->environmentWithOwner();

        $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link", [
            'channels' => ['carrier-pigeon'],
        ])->assertStatus(422);
    }

    public function test_a_non_admin_is_rejected(): void
    {
        Mail::fake();
        [$environment] = $this->environmentWithOwner();
        Sanctum::actingAs(User::factory()->create(['role' => 'company_teacher']));

        $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link")
            ->assertStatus(403);

        Mail::assertNothingQueued();
    }

    public function test_an_unauthenticated_caller_is_rejected(): void
    {
        $this->postJson('/api/admin/environments/1/resend-password-link')->assertStatus(401);
    }

    public function test_a_missing_environment_returns_404(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/admin/environments/999999/resend-password-link')->assertStatus(404);
    }
}
