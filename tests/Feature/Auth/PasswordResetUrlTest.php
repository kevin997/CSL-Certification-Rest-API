<?php

namespace Tests\Feature\Auth;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetUrlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pull the reset URL out of the notification the framework would send.
     */
    private function resetUrlFor(User $user): string
    {
        $notification = new ResetPassword('test-token');

        return $notification->toMail($user)->actionUrl;
    }

    private function environmentOwnedBy(User $user, string $domain): Environment
    {
        $environment = Environment::factory()->create([
            'owner_id' => $user->id,
            'primary_domain' => $domain,
        ]);

        $user->environments()->attach($environment->id, ['role' => 'owner']);

        return $environment;
    }

    public function test_the_reset_link_points_at_the_owned_environment_domain(): void
    {
        $user = User::factory()->create();
        $environment = $this->environmentOwnedBy($user, 'thefirst100academy.csl-brands.com');

        $url = $this->resetUrlFor($user);

        $this->assertStringStartsWith('https://thefirst100academy.csl-brands.com/auth/reset-password?', $url);
        $this->assertStringContainsString('token=test-token', $url);
        $this->assertStringContainsString('environment_id='.$environment->id, $url);
        $this->assertStringContainsString(urlencode($user->email), $url);
    }

    public function test_the_reset_link_never_points_at_the_central_api_host(): void
    {
        $user = User::factory()->create();
        $this->environmentOwnedBy($user, 'thefirst100academy.csl-brands.com');

        $host = parse_url(config('app.url'), PHP_URL_HOST);

        $this->assertNotNull($host);
        $this->assertStringNotContainsString($host, $this->resetUrlFor($user));
    }

    public function test_a_domain_already_carrying_a_scheme_is_not_double_prefixed(): void
    {
        $user = User::factory()->create();
        $this->environmentOwnedBy($user, 'https://already-absolute.csl-brands.com');

        $url = $this->resetUrlFor($user);

        $this->assertStringStartsWith('https://already-absolute.csl-brands.com/auth/reset-password?', $url);
        $this->assertStringNotContainsString('https://https://', $url);
    }

    public function test_a_user_with_no_environment_falls_back_to_the_central_host(): void
    {
        $user = User::factory()->create();

        $url = $this->resetUrlFor($user);

        $this->assertStringContainsString('reset-password', $url);
        $this->assertStringNotContainsString('/auth/reset-password?', $url);
    }

    public function test_an_environment_with_a_blank_domain_falls_back_to_the_central_host(): void
    {
        $user = User::factory()->create();
        $this->environmentOwnedBy($user, '');

        $url = $this->resetUrlFor($user);

        $this->assertStringNotContainsString('/auth/reset-password?', $url);
    }

    public function test_the_forgot_password_endpoint_sends_a_link_to_the_environment_domain(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->environmentOwnedBy($user, 'thefirst100academy.csl-brands.com');

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            return str_starts_with(
                $notification->toMail($user)->actionUrl,
                'https://thefirst100academy.csl-brands.com/auth/reset-password?'
            );
        });
    }
}
