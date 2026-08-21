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
}
