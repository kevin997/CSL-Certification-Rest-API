<?php

namespace Tests\Feature\Mail;

use App\Mail\EnvironmentResetPasswordMail;
use App\Mail\EnvironmentSetupMail;
use App\Mail\LearnerWeeklyDigest;
use App\Models\Environment;
use App\Models\User;
use App\Support\Retention\RetentionLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every tenant link used to be 'https://' . primary_domain . $path, a dead
 * address while the tenant's domain is not live. They now go through TenantUrl:
 * the shared host (carrying environment_id) while pending, the tenant's own
 * domain once verified.
 */
class TenantLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_password_set_mail_links_to_the_shared_host_while_the_domain_is_pending(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'acme.getkursa.space']);
        $mail = new EnvironmentResetPasswordMail('tok', $environment, 'a@b.test', 'a@b.test');

        $with = $mail->content()->with;
        $html = $mail->render();

        $this->assertStringStartsWith('https://app.getkursa.space/auth/reset-password?', $with['resetUrl']);
        $this->assertStringContainsString('environment_id='.$environment->id, $with['resetUrl']);
        $this->assertStringContainsString('app.getkursa.space', $with['pendingDomainNotice']);
        $this->assertStringContainsString('acme.getkursa.space', $with['pendingDomainNotice']);
        $this->assertStringContainsString('app.getkursa.space', $html);
    }

    public function test_the_password_set_mail_links_to_the_tenant_domain_once_live(): void
    {
        $environment = Environment::factory()->create([
            'primary_domain' => 'acme.getkursa.space',
            'domain_verified_at' => now(),
        ]);
        $mail = new EnvironmentResetPasswordMail('tok', $environment, 'a@b.test', 'a@b.test');

        $with = $mail->content()->with;

        $this->assertStringStartsWith('https://acme.getkursa.space/auth/reset-password?', $with['resetUrl']);
        $this->assertNull($with['pendingDomainNotice']);
    }

    public function test_the_setup_mail_and_a_digest_follow_the_same_rule(): void
    {
        $owner = User::factory()->create();
        $pending = Environment::factory()->create([
            'primary_domain' => 'acme.getkursa.space',
            'owner_id' => $owner->id,
        ]);

        $setup = (new EnvironmentSetupMail($pending, $owner, $owner->email, 'pw'))->content()->with;
        $this->assertSame('https://app.getkursa.space/auth/login?environment_id='.$pending->id, $setup['loginUrl']);
        $this->assertNotNull($setup['pendingDomainNotice']);

        $digest = (new LearnerWeeklyDigest($owner, $pending, []))->content()->with;
        $this->assertSame('https://app.getkursa.space/auth/login?environment_id='.$pending->id, $digest['loginUrl']);
    }

    public function test_retention_links_use_the_environment_url_from_the_context(): void
    {
        $links = new RetentionLinks;

        $this->assertSame(
            'https://app.getkursa.space/dashboard?environment_id=5',
            $links->forScenario('instructor_inactive', ['environment_url' => 'https://app.getkursa.space/?environment_id=5'])
        );
        $this->assertSame(
            'https://acme.getkursa.space/',
            $links->forScenario('learner_inactive', ['environment_url' => 'https://acme.getkursa.space/'])
        );
    }
}
