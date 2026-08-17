<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\CertificateTemplateController;
use App\Models\CertificateTemplate;
use App\Models\Environment;
use App\Services\CertificateGenerationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Certificate templates belong to one environment.
 *
 * They used to belong to none: index() returned CertificateTemplate::all(), so
 * every tenant listed every other tenant's templates, and show/setDefault/
 * destroy took a bare id. setDefault cleared is_default across all tenants, and
 * destroy removed the file from the shared certificate service -- breaking
 * certificates belonging to whoever actually owned it.
 */
class CertificateTemplateScopingTest extends TestCase
{
    use RefreshDatabase;

    private Environment $mine;

    private Environment $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mine = Environment::factory()->create();
        $this->theirs = Environment::factory()->create();
    }

    private function controller(): CertificateTemplateController
    {
        // The remote service must not be reached by any of these paths; a
        // strict mock turns an unexpected call into a failure rather than a
        // silent HTTP attempt.
        return new CertificateTemplateController(
            Mockery::mock(CertificateGenerationService::class)
        );
    }

    private function template(?Environment $environment, array $attributes = []): CertificateTemplate
    {
        return CertificateTemplate::create(array_merge([
            'environment_id' => $environment?->id,
            'name' => 'Template',
            'filename' => 'template.pdf',
            'file_path' => 'templates/template.pdf',
            'template_type' => 'completion',
            'is_default' => false,
        ], $attributes));
    }

    private function actingInMyEnvironment(): void
    {
        session(['current_environment_id' => $this->mine->id]);
    }

    public function test_index_lists_only_this_environments_templates(): void
    {
        $mine = $this->template($this->mine, ['name' => 'Mine']);
        $this->template($this->theirs, ['name' => 'Theirs']);
        $this->actingInMyEnvironment();

        $data = $this->controller()->index()->getData(true)['data'];

        $this->assertCount(1, $data);
        $this->assertSame($mine->id, $data[0]['id']);
    }

    public function test_index_hides_templates_owned_by_no_environment(): void
    {
        /* Rows predating the environment_id column. Sharing them by default
           would preserve exactly the leak this change closes. */
        $this->template(null, ['name' => 'Legacy']);
        $this->actingInMyEnvironment();

        $this->assertCount(0, $this->controller()->index()->getData(true)['data']);
    }

    public function test_index_returns_nothing_when_no_environment_is_resolved(): void
    {
        $this->template($this->mine);
        session()->forget('current_environment_id');

        $this->assertCount(0, $this->controller()->index()->getData(true)['data']);
    }

    public function test_show_refuses_another_environments_template(): void
    {
        $theirs = $this->template($this->theirs);
        $this->actingInMyEnvironment();

        $this->expectException(ModelNotFoundException::class);
        $this->controller()->show($theirs->id);
    }

    public function test_show_returns_my_own_template(): void
    {
        $mine = $this->template($this->mine);
        $this->actingInMyEnvironment();

        $data = $this->controller()->show($mine->id)->getData(true)['data'];

        $this->assertSame($mine->id, $data['id']);
    }

    public function test_set_default_leaves_another_environments_default_alone(): void
    {
        $theirs = $this->template($this->theirs, ['is_default' => true]);
        $mine = $this->template($this->mine);
        $this->actingInMyEnvironment();

        $this->controller()->setDefault($mine->id);

        $this->assertTrue($mine->fresh()->is_default);
        $this->assertTrue(
            $theirs->fresh()->is_default,
            'Another environment default must survive this one choosing its own.'
        );
    }

    public function test_set_default_still_clears_my_other_defaults(): void
    {
        $previous = $this->template($this->mine, ['is_default' => true]);
        $next = $this->template($this->mine);
        $this->actingInMyEnvironment();

        $this->controller()->setDefault($next->id);

        $this->assertFalse($previous->fresh()->is_default);
        $this->assertTrue($next->fresh()->is_default);
    }

    public function test_destroy_refuses_another_environments_template(): void
    {
        $theirs = $this->template($this->theirs);
        $this->actingInMyEnvironment();

        try {
            $this->controller()->destroy($theirs->id);
            $this->fail('Deleting another environment template must not be possible.');
        } catch (ModelNotFoundException) {
            // Expected. The strict mock also proves the shared certificate
            // service was never asked to delete the file.
        }

        $this->assertNotSoftDeleted($theirs);
    }

    public function test_get_default_template_is_scoped_to_the_environment(): void
    {
        $this->template($this->theirs, ['is_default' => true, 'name' => 'Theirs']);
        $this->actingInMyEnvironment();

        $this->assertNull(CertificateTemplate::getDefaultTemplate('completion'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
