<?php

namespace Tests\Feature\Marketing;

use App\Services\Marketing\FeatureInventoryService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The marketing content engine broadcasts to a customer WhatsApp group. It used
 * to mine every markdown file under docs/ for "sellable features", which is
 * internal engineering material, so customers received tips like:
 *
 *   "Implement the Factory Pattern - Creating Multiple Payment Gateways"
 *   "Easy deployment cycles for independent teams"
 *   "Real Product Value Tracking"
 *
 * (all three confirmed in production marketing_messages.source rows, sourced
 * from docs/architecture/11-backend-architecture.md,
 * docs/architecture/12-unified-project-structure.md and
 * docs/ENROLLMENT_CODE_COMMISSION_TRACKING.md respectively).
 *
 * The corpus is now an explicit allowlist that is empty by default.
 */
class MarketingContentSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Never reuse a cached inventory built under the old blanket-glob rules.
        File::delete(storage_path('app/private/marketing/feature-inventory.json'));
    }

    public function test_internal_engineering_docs_are_not_mined_for_marketing_features(): void
    {
        config(['services.marketing.feature_docs' => []]);

        $features = (new FeatureInventoryService)->build(fresh: true)['features'];

        $this->assertNotEmpty($features, 'the curated seed list must still be available');

        foreach ($features as $feature) {
            $this->assertSame(
                'seed',
                $feature['doc'],
                "Feature '{$feature['name']}' came from '{$feature['doc']}' — only curated seeds may reach customers."
            );
        }
    }

    /**
     * The engineering docs that actually leaked are still present in the repo,
     * so this asserts the guard is the corpus itself, not their absence.
     */
    public function test_the_offending_docs_still_exist_but_are_not_in_the_corpus(): void
    {
        config(['services.marketing.feature_docs' => []]);

        $this->assertTrue(
            File::exists(base_path('docs/architecture/11-backend-architecture.md')),
            'precondition: the doc that produced the "Factory Pattern" tip still exists'
        );

        $docs = array_column((new FeatureInventoryService)->build(fresh: true)['features'], 'doc');

        $this->assertSame(['seed'], array_values(array_unique($docs)));
    }

    public function test_an_explicitly_allowlisted_document_is_still_readable(): void
    {
        config(['services.marketing.feature_docs' => ['docs/architecture/11-backend-architecture.md']]);

        // We assert the allowlist plumbing resolves the path rather than asserting
        // on AI output, which needs a live Ollama server.
        $service = new FeatureInventoryService;
        $corpus = (new \ReflectionMethod($service, 'corpus'))->invoke($service);

        $this->assertCount(1, $corpus);
        $this->assertStringEndsWith('docs/architecture/11-backend-architecture.md', $corpus[0]);
    }

    public function test_a_missing_allowlisted_document_is_skipped_rather_than_fatal(): void
    {
        config(['services.marketing.feature_docs' => ['docs/this-file-does-not-exist.md']]);

        $service = new FeatureInventoryService;
        $corpus = (new \ReflectionMethod($service, 'corpus'))->invoke($service);

        $this->assertSame([], $corpus);
    }
}
