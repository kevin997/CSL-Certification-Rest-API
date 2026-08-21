<?php

namespace Tests\Feature\Api;

use App\Models\Environment;
use App\Models\Product;
use App\Models\ProductLandingPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLandingPageTest extends TestCase
{
    use RefreshDatabase;

    private function product(Environment $environment): Product
    {
        // There is no ProductFactory in this repo, so build the row directly.
        // name, price and created_by are the only columns without defaults.
        return Product::withoutGlobalScopes()->create([
            'name' => 'Pro Course',
            'slug' => 'pro-course',
            'price' => 49.00,
            'status' => 'active',
            'environment_id' => $environment->id,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_a_landing_page_belongs_to_its_product_and_defaults_to_unpublished(): void
    {
        $environment = Environment::factory()->create();
        $product = $this->product($environment);

        $page = ProductLandingPage::withoutGlobalScopes()->create([
            'product_id' => $product->id,
            'environment_id' => $environment->id,
            'page_data' => ['content' => [], 'root' => ['props' => []]],
        ]);

        $this->assertFalse($page->fresh()->enabled);
        $this->assertSame($product->id, $page->product->id);
        $this->assertIsArray($page->fresh()->page_data);
    }

    public function test_show_returns_an_empty_page_without_creating_a_row(): void
    {
        $environment = Environment::factory()->create();
        $product = $this->product($environment);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['current_environment_id' => $environment->id])
            ->getJson("/api/products/{$product->id}/landing-page");

        $response->assertOk();
        $response->assertJsonPath('data.enabled', false);
        $response->assertJsonPath('data.page_data', null);
        $this->assertDatabaseCount('product_landing_pages', 0);
    }

    public function test_update_creates_then_updates_the_same_row(): void
    {
        $environment = Environment::factory()->create();
        $product = $this->product($environment);
        $user = User::factory()->create();

        $payload = [
            'page_data' => ['content' => [], 'root' => ['props' => []]],
            'seo_title' => 'Buy Pro Course',
            'seo_description' => 'The best course',
        ];

        $this->actingAs($user)
            ->withSession(['current_environment_id' => $environment->id])
            ->putJson("/api/products/{$product->id}/landing-page", $payload)
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['current_environment_id' => $environment->id])
            ->putJson("/api/products/{$product->id}/landing-page", [
                'page_data' => ['content' => [], 'root' => ['props' => []]],
                'seo_title' => 'Buy It Now',
            ])
            ->assertOk();

        $this->assertDatabaseCount('product_landing_pages', 1);
        $this->assertSame('Buy It Now', ProductLandingPage::withoutGlobalScopes()->first()->seo_title);
    }

    public function test_update_leaves_omitted_fields_untouched(): void
    {
        $environment = Environment::factory()->create();
        $product = $this->product($environment);
        $user = User::factory()->create();

        $pageData = ['content' => [['type' => 'Heading']], 'root' => ['props' => []]];

        $this->actingAs($user)
            ->withSession(['current_environment_id' => $environment->id])
            ->putJson("/api/products/{$product->id}/landing-page", [
                'page_data' => $pageData,
                'seo_title' => 'Buy Pro Course',
                'seo_description' => 'The best course',
            ])
            ->assertOk();

        // The SEO panel saves on its own, without the built page in the body.
        $this->actingAs($user)
            ->withSession(['current_environment_id' => $environment->id])
            ->putJson("/api/products/{$product->id}/landing-page", [
                'seo_title' => 'Buy It Now',
            ])
            ->assertOk();

        $page = ProductLandingPage::withoutGlobalScopes()->first();

        $this->assertSame($pageData, $page->page_data);
        $this->assertSame('Buy It Now', $page->seo_title);
        $this->assertSame('The best course', $page->seo_description);
    }

    public function test_public_show_returns_the_published_page_for_a_numeric_environment_id(): void
    {
        $environment = Environment::factory()->create();
        $product = $this->product($environment);

        ProductLandingPage::withoutGlobalScopes()->create([
            'product_id' => $product->id,
            'environment_id' => $environment->id,
            'page_data' => ['content' => [], 'root' => ['props' => []]],
            'enabled' => true,
        ]);

        // The storefront passes its own {domain} URL segment, which is a numeric
        // environment id wherever the tenant has no custom domain.
        $this->getJson('/api/products/public/landing-page?slug='.$product->slug.'&domain='.$environment->id)
            ->assertOk()
            ->assertJsonPath('data.product_id', $product->id);
    }

    public function test_public_show_rejects_a_published_page_with_no_content(): void
    {
        $environment = Environment::factory()->create();
        $product = $this->product($environment);

        // Toggling the switch before the first save leaves exactly this row.
        ProductLandingPage::withoutGlobalScopes()->create([
            'product_id' => $product->id,
            'environment_id' => $environment->id,
            'page_data' => null,
            'enabled' => true,
        ]);

        $this->getJson('/api/products/public/landing-page?slug='.$product->slug.'&domain='.$environment->id)
            ->assertNotFound();
    }

    public function test_toggle_publishes_the_page(): void
    {
        $environment = Environment::factory()->create();
        $product = $this->product($environment);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['current_environment_id' => $environment->id])
            ->postJson("/api/products/{$product->id}/landing-page/toggle", ['enabled' => true])
            ->assertOk();

        $this->assertTrue(ProductLandingPage::withoutGlobalScopes()->first()->enabled);
    }

    public function test_another_environments_product_is_not_found(): void
    {
        $mine = Environment::factory()->create();
        $theirs = Environment::factory()->create();
        $product = $this->product($theirs);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['current_environment_id' => $mine->id])
            ->getJson("/api/products/{$product->id}/landing-page")
            ->assertNotFound();
    }
}
