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
        ]);
        $product->courses()->attach($course->id);

        $form = SalesForm::create([
            'environment_id' => $environment->id,
            'title' => 'Formation en Achat Chine',
            'created_by' => $user->id,
        ]);
        $form->products()->attach($product->id);

        return $form;
    }

    public function test_a_reopened_form_carries_its_products_courses(): void
    {
        $form = $this->form();

        $loaded = SalesForm::with(['fields', 'products.courses:id,title', 'accessBlocks'])
            ->findOrFail($form->id);

        $courses = $loaded->products->flatMap(fn ($product) => $product->courses ?? []);

        $this->assertCount(
            1,
            $courses,
            'The access selector renders from products[].courses; empty means it hides itself.'
        );
        $this->assertSame('Formation en Achat en chine', $courses->first()->title);
    }

    public function test_loading_products_alone_is_what_broke_the_selector(): void
    {
        /* Pins the defect itself, so re-dropping the nested relation fails here
           rather than silently in the UI. */
        $form = $this->form();

        $loaded = SalesForm::with(['fields', 'products', 'accessBlocks'])->findOrFail($form->id);

        $courses = $loaded->products->flatMap(fn ($product) => $product->courses ?? []);

        $this->assertCount(0, $courses);
    }
}
