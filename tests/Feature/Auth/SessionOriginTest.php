<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionOriginTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_root_idp_login_without_a_browser_session_returns_a_safe_error(): void
    {
        $user = User::factory()->create(['password' => bcrypt('synthetic-password')]);

        $this->postJson('https://certification.csl-brands.com/api/session/login', [
            'email' => $user->email,
            'password' => 'synthetic-password',
        ], ['Origin' => 'https://idp.getkursa.space', 'Referer' => 'https://idp.getkursa.space/login'])
            ->assertStatus(419)
            ->assertJsonPath('code', 'browser_session_required');

        $this->assertGuest();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
