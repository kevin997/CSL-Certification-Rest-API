<?php

namespace Tests\Feature\Api;

use App\Models\Environment;
use App\Models\Product;
use App\Models\ProductLandingPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public endpoint is the only one that enforces `enabled`, and it runs
 * without a session -- so it cannot rely on EnvironmentScope and must resolve
 * the tenant from the request domain.
 */
class ProductLandingPagePublicTest extends TestCase
{
    use RefreshDatabase;

    private function productWithPage(bool $enabled): array
    {
        $environment = Environment::factory()->create(['primary_domain' => 'acme.test']);

        $product = Product::withoutGlobalScopes()->create([
            'name' => 'Pro Course',
            'slug' => 'pro-course',
            'price' => 49.00,
            'status' => 'active',
            'environment_id' => $environment->id,
            'created_by' => User::factory()->create()->id,
        ]);

        ProductLandingPage::withoutGlobalScopes()->create([
            'product_id' => $product->id,
            'environment_id' => $environment->id,
            'page_data' => ['content' => [], 'root' => ['props' => []]],
            'seo_title' => 'Buy Pro Course',
            'enabled' => $enabled,
        ]);

        return [$environment, $product];
    }

    public function test_a_published_page_is_returned_for_its_domain(): void
    {
        [$environment, $product] = $this->productWithPage(true);

        $response = $this->getJson('/api/products/public/landing-page?slug=pro-course', [
            'X-Frontend-Domain' => 'acme.test',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.product_id', $product->id);
        $response->assertJsonPath('data.seo_title', 'Buy Pro Course');
    }

    public function test_an_unpublished_page_is_not_found(): void
    {
        $this->productWithPage(false);

        $this->getJson('/api/products/public/landing-page?slug=pro-course', [
            'X-Frontend-Domain' => 'acme.test',
        ])->assertNotFound();
    }

    public function test_an_unknown_slug_is_not_found(): void
    {
        $this->productWithPage(true);

        $this->getJson('/api/products/public/landing-page?slug=nope', [
            'X-Frontend-Domain' => 'acme.test',
        ])->assertNotFound();
    }

    public function test_it_does_not_leak_product_fields(): void
    {
        $this->productWithPage(true);

        $response = $this->getJson('/api/products/public/landing-page?slug=pro-course', [
            'X-Frontend-Domain' => 'acme.test',
        ]);

        $response->assertJsonMissingPath('data.price');
        $response->assertJsonMissingPath('data.created_by');
    }
}
