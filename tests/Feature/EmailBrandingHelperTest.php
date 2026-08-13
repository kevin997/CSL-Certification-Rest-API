<?php

namespace Tests\Feature;

use App\Helpers\EmailBrandingHelper;
use App\Models\Branding;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailBrandingHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_the_active_tenant_brand_and_normalizes_an_already_qualified_domain(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Environment Name',
            'primary_domain' => 'https://academy.example.com',
            'logo_url' => 'https://cdn.example.com/environment.svg',
        ]);

        Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'Tenant Academy',
            'logo_path' => 'branding/tenant-logo.svg',
            'primary_color' => '#123456',
            'secondary_color' => '#abcdef',
            'accent_color' => '#fedcba',
            'font_family' => 'Manrope, sans-serif',
            'is_active' => true,
        ]);

        $resolved = EmailBrandingHelper::resolve($environment);

        $this->assertSame('Tenant Academy', $resolved['company_name']);
        $this->assertSame('http://localhost/storage/branding/tenant-logo.svg', $resolved['logo_url']);
        $this->assertSame('#123456', $resolved['primary_color']);
        $this->assertSame('#abcdef', $resolved['secondary_color']);
        $this->assertSame('#fedcba', $resolved['accent_color']);
        $this->assertSame('Manrope, sans-serif', $resolved['font_family']);
        $this->assertSame('https://academy.example.com/auth/login', $resolved['login_url']);
    }

    public function test_it_uses_the_environment_logo_when_active_branding_has_no_logo(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'logo_url' => 'https://cdn.example.com/environment.svg',
        ]);

        Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'logo_path' => null,
            'is_active' => true,
        ]);

        $resolved = EmailBrandingHelper::resolve($environment);

        $this->assertSame('https://cdn.example.com/environment.svg', $resolved['logo_url']);
    }

    public function test_it_preserves_absolute_http_and_https_branding_logo_urls(): void
    {
        foreach ([
            'http://cdn.example.com/tenant-http.svg',
            'https://cdn.example.com/tenant-https.svg',
        ] as $absoluteLogoUrl) {
            $owner = User::factory()->create();
            $environment = Environment::factory()->create(['owner_id' => $owner->id]);

            Branding::factory()->create([
                'user_id' => $owner->id,
                'environment_id' => $environment->id,
                'logo_path' => $absoluteLogoUrl,
                'is_active' => true,
            ]);

            $resolved = EmailBrandingHelper::resolve($environment);

            $this->assertSame($absoluteLogoUrl, $resolved['logo_url']);
        }
    }

    public function test_it_uses_the_absolute_kursa_logo_and_default_styles_when_no_tenant_logo_exists(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Environment Academy',
            'primary_domain' => 'academy.example.com',
            'logo_url' => null,
        ]);

        $resolved = EmailBrandingHelper::resolve($environment);

        $this->assertSame('Environment Academy', $resolved['company_name']);
        $this->assertSame('http://localhost/images/logo-kursa.svg', $resolved['logo_url']);
        $this->assertSame('#19682f', $resolved['primary_color']);
        $this->assertSame('#f59c00', $resolved['secondary_color']);
        $this->assertSame('#ffb733', $resolved['accent_color']);
        $this->assertSame("'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif", $resolved['font_family']);
        $this->assertSame('https://academy.example.com/auth/login', $resolved['login_url']);
    }

    public function test_it_uses_the_kursa_company_name_when_tenant_and_environment_names_are_missing(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'name' => '',
        ]);

        Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => '',
            'is_active' => true,
        ]);

        $resolved = EmailBrandingHelper::resolve($environment);

        $this->assertSame('KURSA', $resolved['company_name']);
    }

    public function test_it_deterministically_uses_the_most_recent_active_branding_record(): void
    {
        DB::statement('DROP INDEX brandings_active_environment_unique');

        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);

        Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'Stale Academy',
            'updated_at' => now()->subDay(),
            'is_active' => true,
        ]);
        Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'Current Academy',
            'updated_at' => now(),
            'is_active' => true,
        ]);

        $resolved = EmailBrandingHelper::resolve($environment);

        $this->assertSame('Current Academy', $resolved['company_name']);
    }
}
