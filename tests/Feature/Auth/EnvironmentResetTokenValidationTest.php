<?php

namespace Tests\Feature\Auth;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * POST /api/environment-auth/reset-password is public and previously checked
 * only that *some* row existed for the email — it never compared the presented
 * token against the stored hash and never checked expiry. Any superseded link
 * therefore kept working indefinitely, because password_reset_metadata rows
 * survive until a token is used while password_reset_tokens is rotated on each
 * new issuance.
 */
class EnvironmentResetTokenValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Environment $environment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->environment = Environment::factory()->create(['owner_id' => $this->owner->id]);
        $this->owner->environments()->attach($this->environment->id, ['role' => 'owner']);
    }

    /**
     * Issue a link the way LicenceService and PasswordLinkController both do.
     */
    private function issueToken(?string $createdAt = null): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->owner->email],
            ['email' => $this->owner->email, 'token' => Hash::make($token), 'created_at' => $createdAt ?? now()],
        );

        DB::table('password_reset_metadata')->updateOrInsert(
            ['token' => $token],
            [
                'token' => $token,
                'metadata' => json_encode([
                    'environment_id' => $this->environment->id,
                    'environment_email' => $this->owner->email,
                    'is_environment_reset' => true,
                ]),
                'created_at' => $createdAt ?? now(),
            ],
        );

        return $token;
    }

    private function attemptReset(string $token): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/environment-auth/reset-password', [
            'token' => $token,
            'email' => $this->owner->email,
            'environment_id' => $this->environment->id,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);
    }

    public function test_a_current_token_resets_the_password(): void
    {
        $token = $this->issueToken();

        $this->attemptReset($token)->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $this->owner->fresh()->password));
    }

    public function test_a_superseded_token_is_rejected(): void
    {
        $firstToken = $this->issueToken();
        $originalHash = $this->owner->password;

        // A second issuance rotates password_reset_tokens but leaves the first
        // metadata row in place — exactly the production state observed.
        $this->issueToken();

        $this->attemptReset($firstToken)->assertStatus(400);

        $this->assertSame($originalHash, $this->owner->fresh()->password);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $expiry = (int) config('auth.passwords.users.expire', 60);
        $token = $this->issueToken(now()->subMinutes($expiry + 5)->toDateTimeString());
        $originalHash = $this->owner->password;

        $this->attemptReset($token)
            ->assertStatus(400)
            ->assertJsonPath('message', 'This password reset token has expired.');

        $this->assertSame($originalHash, $this->owner->fresh()->password);
    }

    public function test_an_expired_token_is_purged_so_it_cannot_be_retried(): void
    {
        $expiry = (int) config('auth.passwords.users.expire', 60);
        $token = $this->issueToken(now()->subMinutes($expiry + 5)->toDateTimeString());

        $this->attemptReset($token)->assertStatus(400);

        $this->assertDatabaseMissing('password_reset_metadata', ['token' => $token]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $this->owner->email]);
    }

    public function test_a_forged_token_with_no_metadata_is_rejected(): void
    {
        $this->issueToken();
        $originalHash = $this->owner->password;

        $this->attemptReset(Str::random(64))->assertStatus(400);

        $this->assertSame($originalHash, $this->owner->fresh()->password);
    }
}
