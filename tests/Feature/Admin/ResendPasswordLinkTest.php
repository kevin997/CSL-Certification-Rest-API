<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendWhatsAppNotification;
use App\Mail\EnvironmentResetPasswordMail;
use App\Models\Environment;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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
        // The environment's own domain is not verified in this fixture, so the
        // re-sent link points at the shared host and names the environment.
        $this->assertStringStartsWith('https://app.getkursa.space/auth/reset-password', $url);
        $this->assertStringContainsString('environment_id='.$environment->id, $url);

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

    /**
     * Seeds a token row the way LicenceService / this controller persist it,
     * with a created_at in the past so a sloppy rollback that re-stamps now()
     * is caught (expiry is computed from created_at).
     */
    private function seedExistingToken(User $owner): string
    {
        $existingToken = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $owner->email,
            'token' => Hash::make($existingToken),
            'created_at' => now()->subMinutes(30),
        ]);

        return $existingToken;
    }

    /**
     * A support agent clicking "Resend" on a flaky connection must not destroy
     * the owner's only working link: when EVERY attempted channel fails, the
     * previously stored token is restored verbatim so the old URL still
     * verifies — with its ORIGINAL created_at, since restoring with now()
     * would silently extend the old link's life.
     */
    public function test_total_delivery_failure_restores_the_previous_token(): void
    {
        Mail::fake();
        Queue::fake();
        $this->fakeTelegram(succeeds: false);
        $this->actingAsSuperAdmin();
        [$environment, $owner] = $this->environmentWithOwner(['whatsapp_number' => null]);

        $existingToken = $this->seedExistingToken($owner);
        $rowBefore = DB::table('password_reset_tokens')->where('email', $owner->email)->first();

        $response = $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link", [
            'channels' => ['whatsapp', 'telegram'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.delivered', [])
            ->assertJsonPath('data.failed', ['whatsapp', 'telegram'])
            ->assertJsonPath('data.rolled_back', true)
            ->assertJsonPath('data.password_set_url', null);

        $rowAfter = DB::table('password_reset_tokens')->where('email', $owner->email)->first();

        $this->assertTrue(
            Hash::check($existingToken, $rowAfter->token),
            'the owner\'s previous link must still verify after a total delivery failure'
        );
        $this->assertSame($rowBefore->token, $rowAfter->token);
        $this->assertSame(
            $rowBefore->created_at,
            $rowAfter->created_at,
            'created_at must be restored verbatim — expiry is computed from it'
        );

        // The rolled-back issue must not leave metadata for the dead token.
        $this->assertDatabaseCount('password_reset_metadata', 0);
    }

    public function test_total_delivery_failure_with_no_prior_token_leaves_no_orphan_rows(): void
    {
        Mail::fake();
        Queue::fake();
        $this->fakeTelegram(succeeds: false);
        $this->actingAsSuperAdmin();
        [$environment, $owner] = $this->environmentWithOwner();

        $response = $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link", [
            'channels' => ['telegram'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.rolled_back', true)
            ->assertJsonPath('data.password_set_url', null);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $owner->email]);
        $this->assertDatabaseCount('password_reset_metadata', 0);
    }

    /**
     * One working channel is enough: the fresh token reached someone, so it
     * must stand and the superseded link must die — no rollback.
     */
    public function test_partial_delivery_failure_does_not_roll_back(): void
    {
        Mail::fake();
        Queue::fake();
        $this->fakeTelegram(succeeds: false);
        $this->actingAsSuperAdmin();
        [$environment, $owner] = $this->environmentWithOwner();

        $existingToken = $this->seedExistingToken($owner);

        $response = $this->postJson("/api/admin/environments/{$environment->id}/resend-password-link", [
            'channels' => ['email', 'telegram'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.delivered', ['email'])
            ->assertJsonPath('data.failed', ['telegram'])
            ->assertJsonPath('data.rolled_back', false);

        $url = $response->json('data.password_set_url');
        $this->assertNotNull($url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $stored = DB::table('password_reset_tokens')->where('email', $owner->email)->value('token');

        $this->assertTrue(Hash::check($query['token'], $stored), 'the fresh link must verify');
        $this->assertFalse(Hash::check($existingToken, $stored), 'the superseded link must stop working');
        $this->assertDatabaseHas('password_reset_metadata', ['token' => $query['token']]);
    }
}
