<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesForm;
use App\Models\SalesFormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFormWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;
    private Environment $environment;
    private Product $product;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = User::factory()->create(['role' => 'company_teacher']);

        $this->environment = Environment::create([
            'name' => 'Test Environment',
            'primary_domain' => 'test.example.com',
            'slug' => 'test-env',
            'is_active' => true,
            'owner_id' => $this->trainer->id,
        ]);
        session(['current_environment_id' => $this->environment->id]);

        $template = \App\Models\Template::create([
            'title' => 'Sample Template',
            'environment_id' => $this->environment->id,
            'created_by' => $this->trainer->id,
        ]);

        $this->course = Course::create([
            'title' => 'Sample Course',
            'environment_id' => $this->environment->id,
            'created_by' => $this->trainer->id,
            'template_id' => $template->id,
        ]);

        $this->product = Product::create([
            'name' => 'Sample Product',
            'slug' => 'sample-product',
            'price' => 50,
            'currency' => 'USD',
            'status' => 'active',
            'environment_id' => $this->environment->id,
            'created_by' => $this->trainer->id,
        ]);
        $this->product->courses()->attach($this->course->id);
    }

    private function makePublishedForm(): SalesForm
    {
        $form = SalesForm::create([
            'environment_id' => $this->environment->id,
            'created_by' => $this->trainer->id,
            'title' => 'Lead Form',
            'slug' => 'lead-form',
            'status' => SalesForm::STATUS_PUBLISHED,
        ]);
        $form->products()->attach($this->product->id);
        SalesFormField::create([
            'sales_form_id' => $form->id,
            'type' => 'short_text',
            'field_key' => 'city',
            'label' => 'City',
            'is_required' => false,
            'order' => 0,
        ]);

        return $form;
    }

    /** @test */
    public function public_submission_runs_pre_enrollment_workflow(): void
    {
        $form = $this->makePublishedForm();

        $response = $this->postJson("/api/sales-forms/public/{$form->slug}/submit", [
            'name' => 'Jane Learner',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'answers' => ['city' => 'Lagos'],
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['access_code', 'orders' => [['order_id', 'payment_url', 'status']]]);

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);

        // Provisional enrollment created.
        $enrollment = Enrollment::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('course_id', $this->course->id)
            ->first();
        $this->assertNotNull($enrollment);
        $this->assertTrue((bool) $enrollment->is_provisional);
        $this->assertEquals($form->id, $enrollment->sales_form_id);

        // Pending order created.
        $order = Order::withoutGlobalScopes()->where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(Order::TYPE_SALES_FORM, $order->type);
    }

    /** @test */
    public function completing_order_lifts_provisional_access(): void
    {
        $form = $this->makePublishedForm();

        $this->postJson("/api/sales-forms/public/{$form->slug}/submit", [
            'name' => 'Jane Learner',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'answers' => ['city' => 'Lagos'],
        ])->assertStatus(201);

        $user = User::where('email', 'jane@example.com')->first();
        $order = Order::withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($this->trainer)
            ->postJson("/api/sales-forms/orders/{$order->id}/complete")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals(
            Order::STATUS_COMPLETED,
            Order::withoutGlobalScopes()->find($order->id)->status
        );

        $enrollment = Enrollment::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('course_id', $this->course->id)
            ->first();
        $this->assertFalse((bool) $enrollment->is_provisional);
    }
}
