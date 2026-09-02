<?php

namespace Tests\Unit\Tenancy;

use App\Models\Environment;
use App\Models\User;
use App\Support\Tenancy\SwitchTokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SwitchTokenIssuerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_token_payload_under_the_switch_prefix_and_builds_the_redirect(): void
    {
        $user = User::factory()->create();
        $environment = Environment::factory()->create();
        $issuer = new SwitchTokenIssuer;

        $token = $issuer->issue($user, $environment, 300, '7');

        $this->assertSame(64, strlen($token));
        $this->assertSame([
            'user_id' => $user->id,
            'target_environment_id' => $environment->id,
            'source_environment_id' => '7',
        ], collect(Cache::get(SwitchTokenIssuer::CACHE_PREFIX.$token))->except('created_at')->all());
        $this->assertSame(
            'https://app.getkursa.space/auth/switch?token='.$token.'&environment_id='.$environment->id,
            $issuer->redirectUrl($environment, $token)
        );
    }
}
