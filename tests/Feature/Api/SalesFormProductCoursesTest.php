<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Environment;
use App\Models\Product;
use App\Models\SalesForm;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A reopened sales form must carry its products' courses.
 *
 * The access-control selector renders from products[].courses and shows
 * "attach at least one product with a course" when that array is empty. show()
 * eager-loaded `products` but not `products.courses`, so a form that was
 * reopened after creation claimed no product was attached while its products
 * were plainly there. attachableProducts() loads the relation, which is why
 * the selector worked while picking a product and broke only on reload.
 */
class SalesFormProductCoursesTest extends TestCase
{
    use RefreshDatabase;

    private function form(): SalesForm
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create();

        $template = Template::create([
            'title' => 'Achat Chine',
            'created_by' => $user->id,
        ]);

        $course = Course::create([
            'title' => 'Formation en Achat en chine',
            'environment_id' => $environment->id,
            'template_id' => $template->id,
            'created_by' => $user->id,
        ]);

        $product = Product::create([
            'name' => 'Achat Chine',
            'environment_id' => $environment->id,
            'created_by' => $user->id,
            'price' => 0,
        ]);
        $product->courses()->attach($course->id);

        $form = SalesForm::create([
            'environment_id' => $environment->id,
            'title' => 'Formation en Achat Chine',
            'created_by' => $user->id,
            'slug' => 'formation-en-achat-chine',
        ]);
        $form->products()->attach($product->id);

        return $form;
    }

    /**
     * Serialised, not the object graph: accessing $product->courses in PHP
     * lazily loads it, so the graph always looks right. Eloquent only
     * serialises relations that were LOADED, and the serialised payload is
     * what the selector actually receives.
     */
    private function serialisedProducts(array $with): array
    {
        $form = SalesForm::with($with)->findOrFail($this->form()->id);

        return $form->toArray()['products'] ?? [];
    }

    public function test_a_reopened_form_serialises_its_products_courses(): void
    {
        $products = $this->serialisedProducts(['fields', 'products.courses:id,title', 'accessBlocks']);

        $this->assertArrayHasKey('courses', $products[0]);
        $this->assertSame('Formation en Achat en chine', $products[0]['courses'][0]['title']);
    }

    public function test_loading_products_alone_omits_courses_from_the_payload(): void
    {
        /* The defect itself: the selector reads products[].courses, finds it
           absent, and hides behind "attach at least one product with a
           course" while the products are plainly attached. */
        $products = $this->serialisedProducts(['fields', 'products', 'accessBlocks']);

        $this->assertNotEmpty($products, 'the product is attached either way');
        $this->assertArrayNotHasKey('courses', $products[0]);
    }
}
