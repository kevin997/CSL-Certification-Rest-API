<?php

namespace Tests\Unit\Tenancy;

use App\Models\Environment;
use App\Support\Tenancy\TenantUrl;
use Tests\TestCase;

class TenantUrlTest extends TestCase
{
    private function environment(array $attributes = []): Environment
    {
        return (new Environment)->forceFill($attributes + [
            'id' => 42,
            'primary_domain' => 'acme.getkursa.space',
            'domain_verified_at' => null,
        ]);
    }

    public function test_a_pending_domain_links_to_the_shared_host_with_the_environment_id(): void
    {
        $url = TenantUrl::to($this->environment(), '/auth/login');

        $this->assertSame('https://app.getkursa.space/auth/login?environment_id=42', $url);
        $this->assertSame('https://app.getkursa.space', TenantUrl::base($this->environment()));
        $this->assertFalse(TenantUrl::isLive($this->environment()));
    }

    public function test_a_live_domain_links_to_the_tenant_domain_without_the_environment_id(): void
    {
        $environment = $this->environment(['domain_verified_at' => now()]);

        $this->assertSame('https://acme.getkursa.space/auth/login', TenantUrl::to($environment, 'auth/login'));
        $this->assertSame('https://acme.getkursa.space', TenantUrl::base($environment));
    }

    public function test_query_parameters_are_appended_and_an_explicit_environment_id_is_not_duplicated(): void
    {
        $url = TenantUrl::to($this->environment(), '/auth/reset-password', ['token' => 'abc', 'environment_id' => 42]);

        $this->assertSame('https://app.getkursa.space/auth/reset-password?token=abc&environment_id=42', $url);
    }

    public function test_a_scheme_in_primary_domain_is_stripped(): void
    {
        $environment = $this->environment(['primary_domain' => 'https://acme.getkursa.space/', 'domain_verified_at' => now()]);

        $this->assertSame('https://acme.getkursa.space/dashboard', TenantUrl::to($environment, '/dashboard'));
    }

    public function test_localhost_hosts_use_http(): void
    {
        $environment = $this->environment(['primary_domain' => 'localhost:3000', 'domain_verified_at' => now()]);

        $this->assertSame('http://localhost:3000', TenantUrl::base($environment));
        $this->assertSame('http', TenantUrl::scheme('127.0.0.1'));
        $this->assertSame('https', TenantUrl::scheme('acme.getkursa.space'));
    }

    public function test_the_shared_host_is_configurable(): void
    {
        config(['tenancy.shared_host' => 'app.example.test']);

        $this->assertSame('https://app.example.test', TenantUrl::base($this->environment()));
    }
}
