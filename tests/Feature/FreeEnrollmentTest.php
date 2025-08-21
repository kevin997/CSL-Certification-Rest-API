<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class FreeEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Environment $environment;
    private ProductCategory $category;
    private Course $course;

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
        
        // Create test course
        $this->course = Course::create([
            'title' => 'Test Course',
            'slug' => 'test-course',
            'description' => 'A test course',
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
            'status' => 'published',
        ]);
    }

    /** @test */
    public function authenticated_user_can_enroll_in_free_course()
    {
        // Create a free product
        $product = Product::create([
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
        ]);

        // Associate course with product
        DB::table('product_courses')->insert([
            'product_id' => $product->id,
            'course_id' => $this->course->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Make authenticated request
        $response = $this->actingAs($this->user)
            ->postJson("/api/storefront/{$this->environment->id}/enroll-free", [
                'product_id' => $product->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Successfully enrolled in course',
            'data' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
            ]
        ]);

        // Verify enrollment was created
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'environment_id' => $this->environment->id,
            'status' => Enrollment::STATUS_ENROLLED,
        ]);

        // Verify response structure includes enrollment data
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'enrollments' => [
                    '*' => [
                        'enrollment_id',
                        'course_id',
                        'status',
                        'enrolled_at',
                    ]
                ],
                'product_id',
                'product_name',
            ]
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_enroll_in_free_course()
    {
        // Create a free product
        $product = Product::create([
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
        ]);

        // Make unauthenticated request
        $response = $this->postJson("/api/storefront/{$this->environment->id}/enroll-free", [
            'product_id' => $product->id,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function user_cannot_enroll_in_paid_course_via_free_endpoint()
    {
        // Create a paid product
        $product = Product::create([
            'name' => 'Paid Test Course',
            'description' => 'A paid course for testing',
            'price' => 99.99,
            'currency' => 'USD',
            'is_subscription' => false,
            'is_free' => false,
            'status' => 'active',
            'category_id' => $this->category->id,
            'sku' => 'PAID-TEST-001',
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
        ]);

        // Make authenticated request
        $response = $this->actingAs($this->user)
            ->postJson("/api/storefront/{$this->environment->id}/enroll-free", [
                'product_id' => $product->id,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'This course requires payment',
            'error_code' => 'COURSE_NOT_FREE'
        ]);

        // Verify no enrollment was created
        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'environment_id' => $this->environment->id,
        ]);
    }

    /** @test */
    public function user_cannot_enroll_twice_in_same_course()
    {
        // Create a free product
        $product = Product::create([
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
        ]);

        // Associate course with product
        DB::table('product_courses')->insert([
            'product_id' => $product->id,
            'course_id' => $this->course->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create existing enrollment
        Enrollment::create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'environment_id' => $this->environment->id,
            'status' => Enrollment::STATUS_ENROLLED,
            'progress_percentage' => 0,
            'last_activity_at' => now(),
            'enrolled_at' => now(),
        ]);

        // Try to enroll again
        $response = $this->actingAs($this->user)
            ->postJson("/api/storefront/{$this->environment->id}/enroll-free", [
                'product_id' => $product->id,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'You are already enrolled in this course',
            'error_code' => 'ALREADY_ENROLLED'
        ]);

        // Verify only one enrollment exists
        $this->assertEquals(1, Enrollment::where('user_id', $this->user->id)
            ->where('course_id', $this->course->id)
            ->where('environment_id', $this->environment->id)
            ->count());
    }

    /** @test */
    public function enrollment_fails_for_nonexistent_product()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/storefront/{$this->environment->id}/enroll-free", [
                'product_id' => 99999,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_id']);
    }

    /** @test */
    public function enrollment_fails_for_nonexistent_environment()
    {
        // Create a free product
        $product = Product::create([
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
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/storefront/99999/enroll-free", [
                'product_id' => $product->id,
            ]);

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Environment not found'
        ]);
    }

    /** @test */
    public function enrollment_fails_for_product_in_different_environment()
    {
        // Create another environment
        $otherEnvironment = Environment::create([
            'name' => 'Other Environment',
            'primary_domain' => 'other.example.com',
            'slug' => 'other-env',
        ]);

        // Create a free product in different environment
        $product = Product::create([
            'name' => 'Free Test Course',
            'description' => 'A free course for testing',
            'price' => 0,
            'currency' => 'USD',
            'is_subscription' => false,
            'is_free' => true,
            'status' => 'active',
            'category_id' => $this->category->id,
            'sku' => 'FREE-TEST-001',
            'environment_id' => $otherEnvironment->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/storefront/{$this->environment->id}/enroll-free", [
                'product_id' => $product->id,
            ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Product not found'
        ]);
    }

    /** @test */
    public function enrollment_handles_product_with_multiple_courses()
    {
        // Create additional course
        $course2 = Course::create([
            'title' => 'Test Course 2',
            'slug' => 'test-course-2',
            'description' => 'A second test course',
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
            'status' => 'published',
        ]);

        // Create a free product
        $product = Product::create([
            'name' => 'Free Bundle Course',
            'description' => 'A free course bundle for testing',
            'price' => 0,
            'currency' => 'USD',
            'is_subscription' => false,
            'is_free' => true,
            'status' => 'active',
            'category_id' => $this->category->id,
            'sku' => 'FREE-BUNDLE-001',
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
        ]);

        // Associate both courses with product
        DB::table('product_courses')->insert([
            [
                'product_id' => $product->id,
                'course_id' => $this->course->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => $product->id,
                'course_id' => $course2->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // Make authenticated request
        $response = $this->actingAs($this->user)
            ->postJson("/api/storefront/{$this->environment->id}/enroll-free", [
                'product_id' => $product->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Successfully enrolled in course',
        ]);

        // Verify both enrollments were created
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'environment_id' => $this->environment->id,
            'status' => Enrollment::STATUS_ENROLLED,
        ]);

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->user->id,
            'course_id' => $course2->id,
            'environment_id' => $this->environment->id,
            'status' => Enrollment::STATUS_ENROLLED,
        ]);

        // Verify response includes both enrollments
        $responseData = $response->json();
        $this->assertCount(2, $responseData['data']['enrollments']);
    }

    /** @test */
    public function enrollment_validation_requires_product_id()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/storefront/{$this->environment->id}/enroll-free", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_id']);
    }
}