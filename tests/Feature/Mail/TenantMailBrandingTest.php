<?php

namespace Tests\Feature\Mail;

use App\Mail\EnvironmentResetPasswordMail;
use App\Mail\InvoiceMail;
use App\Mail\WelcomeToEnvironment;
use App\Models\Branding;
use App\Models\Environment;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMailBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://platform.test',
            'mail.from.address' => 'mail@platform.test',
        ]);
        app('url')->forceRootUrl('https://platform.test');
        app('url')->forceScheme('https');
    }

    public function test_reset_password_mail_renders_active_tenant_branding_and_uses_it_in_the_envelope(): void
    {
        [$environment] = $this->tenantWithBranding();
        $mail = new EnvironmentResetPasswordMail(
            'reset-token',
            $environment,
            'learner@tenant.test',
            'account@example.test',
        );

        $branding = $mail->content()->with['branding'];
        $html = $mail->render();
        $envelope = $mail->envelope();

        $this->assertIsArray($branding);
        $this->assertSame('Tenant Learning', $branding['company_name']);
        $this->assertSame('https://platform.test/storage/tenant/logo.png', $branding['logo_url']);
        $this->assertStringContainsString('Tenant Learning', $html);
        $this->assertStringContainsString('https://platform.test/storage/tenant/logo.png', $html);
        $this->assertStringContainsString('#123456', $html);
        $this->assertStringContainsString('tenant.example.test/auth/reset-password?', $html);
        $this->assertSame('Tenant Learning', $envelope->from->name);
        $this->assertSame('Reset Password for Tenant Learning', $envelope->subject);
    }

    public function test_reset_password_mail_uses_environment_branding_fallbacks(): void
    {
        $environment = $this->tenantWithoutBranding();
        $mail = new EnvironmentResetPasswordMail(
            'reset-token',
            $environment,
            'learner@tenant.test',
            'account@example.test',
        );

        $branding = $mail->content()->with['branding'];
        $html = $mail->render();

        $this->assertSame('Fallback Academy', $branding['company_name']);
        $this->assertSame('https://cdn.example.test/fallback-logo.svg', $branding['logo_url']);
        $this->assertStringContainsString('Fallback Academy', $html);
        $this->assertStringContainsString('https://cdn.example.test/fallback-logo.svg', $html);
    }

    public function test_reset_password_mail_forces_https_and_removes_a_trailing_domain_slash(): void
    {
        $environment = $this->tenantWithoutBranding();
        $environment->primary_domain = 'http://fallback.example.test/';
        $mail = new EnvironmentResetPasswordMail(
            'reset-token',
            $environment,
            'learner@tenant.test',
            'account@example.test',
        );

        $resetUrl = $mail->content()->with['resetUrl'];

        $this->assertSame(
            'https://fallback.example.test/auth/reset-password?token=reset-token&email=account%40example.test&environment_id='.$environment->id,
            $resetUrl,
        );
    }

    public function test_welcome_mail_renders_active_tenant_branding_and_uses_it_in_the_envelope(): void
    {
        [$environment, $user] = $this->tenantWithBranding();
        $mail = new WelcomeToEnvironment($user, $environment, 'temporary-password');

        $branding = $mail->content()->with['branding'];
        $html = $mail->render();
        $envelope = $mail->envelope();

        $this->assertIsArray($branding);
        $this->assertSame('Tenant Learning', $branding['company_name']);
        $this->assertStringContainsString('Tenant Learning', $html);
        $this->assertStringContainsString('https://platform.test/storage/tenant/logo.png', $html);
        $this->assertStringContainsString('#123456', $html);
        $this->assertStringContainsString('https://tenant.example.test/auth/login', $html);
        $this->assertSame('Tenant Learning', $envelope->from->name);
        $this->assertSame('Welcome to Tenant Learning', $envelope->subject);
    }

    public function test_welcome_mail_uses_environment_branding_fallbacks(): void
    {
        $environment = $this->tenantWithoutBranding();
        $user = User::factory()->create();
        $mail = new WelcomeToEnvironment($user, $environment);

        $branding = $mail->content()->with['branding'];
        $html = $mail->render();

        $this->assertSame('Fallback Academy', $branding['company_name']);
        $this->assertSame('https://cdn.example.test/fallback-logo.svg', $branding['logo_url']);
        $this->assertStringContainsString('Fallback Academy', $html);
        $this->assertStringContainsString('https://cdn.example.test/fallback-logo.svg', $html);
    }

    public function test_invoice_mail_renders_active_tenant_branding_and_uses_it_in_the_envelope(): void
    {
        [$environment] = $this->tenantWithBranding();
        $mail = new InvoiceMail($this->invoiceFor($environment));

        $branding = $mail->content()->with['branding'];
        $html = $mail->render();
        $envelope = $mail->envelope();

        $this->assertIsArray($branding);
        $this->assertSame('Tenant Learning', $branding['company_name']);
        $this->assertStringContainsString('Tenant Learning', $html);
        $this->assertStringContainsString('https://platform.test/storage/tenant/logo.png', $html);
        $this->assertStringContainsString('#123456', $html);
        $this->assertStringContainsString('INV-1001', $html);
        $this->assertStringContainsString('1,250.00 USD', $html);
        $this->assertStringContainsString('https://billing.example.test/pay/INV-1001', $html);
        $this->assertSame('Tenant Learning', $envelope->from->name);
        $this->assertSame('Platform Fee Invoice — INV-1001', $envelope->subject);
    }

    public function test_invoice_mail_uses_environment_branding_fallbacks(): void
    {
        $environment = $this->tenantWithoutBranding();
        $mail = new InvoiceMail($this->invoiceFor($environment));

        $branding = $mail->content()->with['branding'];
        $html = $mail->render();

        $this->assertSame('Fallback Academy', $branding['company_name']);
        $this->assertSame('https://cdn.example.test/fallback-logo.svg', $branding['logo_url']);
        $this->assertStringContainsString('Fallback Academy', $html);
        $this->assertStringContainsString('https://cdn.example.test/fallback-logo.svg', $html);
    }

    /**
     * @return array{Environment, User}
     */
    private function tenantWithBranding(): array
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Environment Name',
            'primary_domain' => 'tenant.example.test',
            'logo_url' => 'https://cdn.example.test/environment-logo.svg',
        ]);

        Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'Tenant Learning',
            'logo_path' => 'tenant/logo.png',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'accent_color' => '#abcdef',
            'font_family' => 'Tenant Sans',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        return [$environment, $user];
    }

    private function tenantWithoutBranding(): Environment
    {
        $owner = User::factory()->create();

        return Environment::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Fallback Academy',
            'primary_domain' => 'fallback.example.test',
            'logo_url' => 'https://cdn.example.test/fallback-logo.svg',
        ]);
    }

    private function invoiceFor(Environment $environment): Invoice
    {
        return Invoice::create([
            'environment_id' => $environment->id,
            'invoice_number' => 'INV-1001',
            'month' => '2026-08-01',
            'total_fee_amount' => 1250,
            'currency' => 'USD',
            'status' => 'sent',
            'due_date' => '2026-08-31',
            'payment_link' => 'https://billing.example.test/pay/INV-1001',
            'transaction_count' => 4,
        ]);
    }
}
