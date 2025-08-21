<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductFreeCoursesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Environment $environment;
    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create test environment
        $this->environment = Environment::create([
            'name' => 'Test Environment',
            'primary_domain' => 'test.example.com',
            'slug' => 'test-env',
        ]);
        
        // Create test category
        $this->category = ProductCategory::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_create_free_product()
    {
        $productData = [
            'name' => 'Free Test Course',
            'description' => 'A free course for testing',
            'price' => 0,
            'currency' => 'USD',
            'is_subscription' => false,
            'is_free' => true,
            'status' => 'active',
            'category_id' => $this->category->id,
            'sku' => 'FREE-TEST-001',
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
        ];

        $product = Product::create($productData);

        $this->assertTrue($product->is_free);
        $this->assertEquals(0, $product->price);
        $this->assertEquals('Free Test Course', $product->name);
    }

    /** @test */
    public function it_defaults_is_free_to_false_for_existing_products()
    {
        $productData = [
            'name' => 'Paid Test Course',
            'description' => 'A paid course for testing',
            'price' => 99.99,
            'currency' => 'USD',
            'is_subscription' => false,
            'status' => 'active',
            'category_id' => $this->category->id,
            'sku' => 'PAID-TEST-001',
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
        ];

        $product = Product::create($productData);

        $this->assertFalse($product->is_free);
        $this->assertEquals(99.99, $product->price);
    }

    /** @test */
    public function storefront_api_returns_is_free_field()
    {
        // Create a free product
        $freeProduct = Product::create([
            'name' => 'Free API Test Course',
            'description' => 'Testing API response',
            'price' => 0,
            'currency' => 'USD',
            'is_subscription' => false,
            'is_free' => true,
            'status' => 'active',
            'category_id' => $this->category->id,
            'sku' => 'FREE-API-001',
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
        ]);

        // Create a paid product
        $paidProduct = Product::create([
            'name' => 'Paid API Test Course',
            'description' => 'Testing API response',
            'price' => 49.99,
            'currency' => 'USD',
            'is_subscription' => false,
            'is_free' => false,
            'status' => 'active',
            'category_id' => $this->category->id,
            'sku' => 'PAID-API-001',
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
        ]);

        // Test storefront products endpoint
        $response = $this->getJson("/api/storefront/{$this->environment->id}/products");
        
        $response->assertStatus(200);
        
        $products = $response->json('data');
        $this->assertCount(2, $products);
        
        // Check that is_free field is present
        foreach ($products as $product) {
            $this->assertArrayHasKey('is_free', $product);
            $this->assertIsBool($product['is_free']);
        }
        
        // Verify specific products
        $freeProductFromApi = collect($products)->firstWhere('id', $freeProduct->id);
        $paidProductFromApi = collect($products)->firstWhere('id', $paidProduct->id);
        
        $this->assertTrue($freeProductFromApi['is_free']);
        $this->assertFalse($paidProductFromApi['is_free']);
    }

    /** @test */
    public function storefront_individual_product_api_returns_is_free_field()
    {
        $freeProduct = Product::create([
            'name' => 'Individual Free Course',
            'description' => 'Testing individual API response',
            'price' => 0,
            'currency' => 'USD',
            'is_subscription' => false,
            'is_free' => true,
            'status' => 'active',
            'category_id' => $this->category->id,
            'sku' => 'INDIVIDUAL-FREE-001',
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/storefront/{$this->environment->id}/products/{$freeProduct->id}");
        
        $response->assertStatus(200);
        
        $product = $response->json('data');
        $this->assertArrayHasKey('is_free', $product);
        $this->assertTrue($product['is_free']);
        $this->assertEquals('Individual Free Course', $product['name']);
    }
}
