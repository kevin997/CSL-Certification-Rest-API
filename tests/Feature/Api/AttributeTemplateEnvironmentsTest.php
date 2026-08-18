<?php

namespace Tests\Feature\Api;

use App\Models\CertificateTemplate;
use App\Models\Environment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one-off command that gives pre-existing templates an owner.
 *
 * The derivation from course usage is exercised against real data by running
 * the command without --apply; what is pinned here is the safety behaviour:
 * a dry run writes nothing, an explicit assignment is honoured, and a bad
 * --assign is refused rather than silently skipped.
 */
class AttributeTemplateEnvironmentsTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND = 'certificates:attribute-template-environments';

    private function template(?Environment $environment = null): CertificateTemplate
    {
        return CertificateTemplate::create([
            'environment_id' => $environment?->id,
            'name' => 'Legacy',
            'filename' => 'legacy.pdf',
            'file_path' => 'templates/legacy.pdf',
            'template_type' => 'completion',
            'is_default' => false,
        ]);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $environment = Environment::factory()->create();
        $template = $this->template();

        $this->artisan(self::COMMAND, ['--assign' => ["{$template->id}:{$environment->id}"]])
            ->assertSuccessful();

        $this->assertNull($template->fresh()->environment_id);
    }

    public function test_an_explicit_assignment_is_applied(): void
    {
        $environment = Environment::factory()->create();
        $template = $this->template();

        $this->artisan(self::COMMAND, [
            '--apply' => true,
            '--assign' => ["{$template->id}:{$environment->id}"],
        ])->assertSuccessful();

        $this->assertSame($environment->id, $template->fresh()->environment_id);
    }

    public function test_a_template_with_no_usage_is_left_unowned(): void
    {
        $template = $this->template();

        $this->artisan(self::COMMAND, ['--apply' => true])->assertSuccessful();

        $this->assertNull($template->fresh()->environment_id);
    }

    public function test_an_unknown_environment_is_refused(): void
    {
        $template = $this->template();

        $this->expectException(\InvalidArgumentException::class);

        $this->artisan(self::COMMAND, [
            '--apply' => true,
            '--assign' => ["{$template->id}:999999"],
        ])->run();
    }

    public function test_a_malformed_assignment_is_refused(): void
    {
        $this->template();

        $this->expectException(\InvalidArgumentException::class);

        $this->artisan(self::COMMAND, ['--apply' => true, '--assign' => ['nonsense']])->run();
    }

    public function test_already_owned_templates_are_left_alone(): void
    {
        $owner = Environment::factory()->create();
        $other = Environment::factory()->create();
        $template = $this->template($owner);

        $this->artisan(self::COMMAND, [
            '--apply' => true,
            '--assign' => ["{$template->id}:{$other->id}"],
        ])->assertSuccessful();

        $this->assertSame(
            $owner->id,
            $template->fresh()->environment_id,
            'The command only claims unowned templates.'
        );
    }
}
