<?php

namespace Tests\Unit\Tenancy;

use App\Support\Tenancy\TenantDomain;
use RuntimeException;
use Tests\TestCase;

class TenantDomainTest extends TestCase
{
    public function test_a_subdomain_label_is_composed_under_the_configured_base(): void
    {
        $this->assertSame('acme.getkursa.space', TenantDomain::compose('subdomain', 'Acme'));
        $this->assertSame('acme.getkursa.space', TenantDomain::compose('subdomain', 'https://acme'));
    }

    public function test_a_fully_qualified_kursa_or_legacy_host_is_reduced_to_its_label_first(): void
    {
        $this->assertSame('acme.getkursa.space', TenantDomain::compose('subdomain', 'acme.getkursa.space'));
        $this->assertSame('acme.getkursa.space', TenantDomain::compose('subdomain', 'acme.csl-brands.com'));
    }

    public function test_the_base_is_configurable(): void
    {
        config(['tenancy.subdomain_base' => 'example.test']);

        $this->assertSame('acme.example.test', TenantDomain::compose('subdomain', 'acme'));
    }

    public function test_a_custom_domain_is_lowercased_and_stripped_of_its_scheme(): void
    {
        $this->assertSame('learn.acme.com', TenantDomain::compose('custom', 'https://Learn.Acme.com/'));
    }

    public function test_invalid_and_reserved_labels_are_rejected(): void
    {
        foreach (['ap', '-acme', 'acme-', 'ac me', 'a.b', 'app', 'www', 'manager'] as $label) {
            try {
                TenantDomain::compose('subdomain', $label);
                $this->fail("Expected {$label} to be rejected");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('subdomain', strtolower($e->getMessage()));
            }
        }
    }

    public function test_label_of_and_is_kursa_subdomain(): void
    {
        $this->assertSame('acme', TenantDomain::labelOf('acme.getkursa.space'));
        $this->assertSame('acme', TenantDomain::labelOf('acme.cfpcsl.com'));
        $this->assertNull(TenantDomain::labelOf('learn.acme.com'));
        $this->assertTrue(TenantDomain::isKursaSubdomain('acme.csl-brands.com'));
        $this->assertFalse(TenantDomain::isKursaSubdomain('getkursa.space'));
        $this->assertFalse(TenantDomain::isKursaSubdomain('learn.acme.com'));
    }
}
