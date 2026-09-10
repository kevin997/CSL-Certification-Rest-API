<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Referral;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Staff actions are authorised by standing in the academy the record belongs
 * to, never by the global role.
 *
 * The global role says only that an account owns an academy somewhere. Every
 * isTeacher() gate therefore opened for a teacher who was merely a LEARNER in
 * another academy — resetting classmates' progress, deleting that academy's
 * products, changing its orders — and, on records not scoped to an environment
 * at all, for any teacher anywhere. Each test states the action that leaks if
 * the guard it exercises is removed.
 */
class StaffAuthorizationPerEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    private Environment $bootcamps;

    private User $bootcampsOwner;

    /** Owns Satoshi School, and is only a learner in BootCamps. */
    private User $teacherFromElsewhere;

    private User $classmate;

    private Enrollment $classmateEnrollment;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config(['licensing.enforcement_enabled' => false]);

        $this->bootcampsOwner = User::factory()->create(['role' => 'company_teacher']);
        $this->bootcamps = Environment::create([
            'name' => 'BootCamps', 'primary_domain' => 'bootcamps.example.com',
            'slug' => 'bootcamps', 'owner_id' => $this->bootcampsOwner->id,
        ]);

        $this->teacherFromElsewhere = User::factory()->create(['role' => 'company_teacher']);
        Environment::create([
            'name' => 'Satoshi School', 'primary_domain' => 'satoshi.example.com',
            'slug' => 'satoshi', 'owner_id' => $this->teacherFromElsewhere->id,
        ]);
        EnvironmentUser::create([
            'environment_id' => $this->bootcamps->id,
            'user_id' => $this->teacherFromElsewhere->id,
            'role' => 'learner',
        ]);

        $this->classmate = User::factory()->create(['role' => 'learner']);
        EnvironmentUser::create(['environment_id' => $this->bootcamps->id, 'user_id' => $this->classmate->id, 'role' => 'learner']);

        $template = Template::create(['title' => 'T', 'environment_id' => $this->bootcamps->id, 'created_by' => $this->bootcampsOwner->id]);
        $course = Course::create([
            'title' => 'Data', 'slug' => 'data', 'description' => 'x', 'status' => 'published',
            'environment_id' => $this->bootcamps->id, 'created_by' => $this->bootcampsOwner->id, 'template_id' => $template->id,
        ]);
        $this->classmateEnrollment = Enrollment::create([
            'user_id' => $this->classmate->id, 'course_id' => $course->id, 'environment_id' => $this->bootcamps->id,
            'status' => Enrollment::STATUS_ENROLLED, 'progress_percentage' => 60, 'last_activity_at' => now(),
        ]);

        $category = ProductCategory::create([
            'name' => 'Data', 'slug' => 'data', 'environment_id' => $this->bootcamps->id, 'created_by' => $this->bootcampsOwner->id,
        ]);
        $this->product = Product::create([
            'name' => 'Bootcamp', 'description' => 'x', 'price' => 50000, 'currency' => 'XAF',
            'is_subscription' => false, 'status' => 'active', 'category_id' => $category->id, 'sku' => 'BC-1',
            'environment_id' => $this->bootcamps->id, 'created_by' => $this->bootcampsOwner->id,
        ]);
    }

    /** Signed in to BootCamps — the environment every scoped lookup resolves against. */
    private function in(User $user)
    {
        return $this->actingAs($user)->withSession(['current_environment_id' => $this->bootcamps->id]);
    }

    // ---------------------------------------------------------- the primitive

    public function test_owning_an_academy_elsewhere_does_not_make_you_staff_here(): void
    {
        $this->assertFalse($this->teacherFromElsewhere->isStaffIn($this->bootcamps->id));
        $this->assertTrue($this->bootcampsOwner->isStaffIn($this->bootcamps->id));
    }

    public function test_learner_roles_are_not_staff_and_unknown_roles_deny(): void
    {
        $companyLearner = User::factory()->create(['role' => 'learner']);
        EnvironmentUser::create(['environment_id' => $this->bootcamps->id, 'user_id' => $companyLearner->id, 'role' => 'company_learner']);
        $oddRole = User::factory()->create(['role' => 'learner']);
        EnvironmentUser::create(['environment_id' => $this->bootcamps->id, 'user_id' => $oddRole->id, 'role' => 'something_new']);
        $teamMember = User::factory()->create(['role' => 'learner']);
        EnvironmentUser::create(['environment_id' => $this->bootcamps->id, 'user_id' => $teamMember->id, 'role' => 'company_team_member']);

        $this->assertFalse($companyLearner->isStaffIn($this->bootcamps->id));
        $this->assertFalse($oddRole->isStaffIn($this->bootcamps->id), 'an unanticipated role must deny');
        $this->assertTrue($teamMember->isStaffIn($this->bootcamps->id));
    }

    public function test_platform_admins_are_staff_everywhere(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->assertTrue($admin->isStaffIn($this->bootcamps->id));
    }

    // -------------------------------------- scoped records: a learner here gets no staff powers

    public function test_a_teacher_who_is_only_a_learner_here_cannot_reset_a_classmates_progress(): void
    {
        $this->in($this->teacherFromElsewhere)
            ->postJson("/api/enrollments/{$this->classmateEnrollment->id}/activity-completions/reset-all")
            ->assertForbidden();
    }

    public function test_a_teacher_who_is_only_a_learner_here_cannot_read_a_classmates_progress(): void
    {
        $this->in($this->teacherFromElsewhere)
            ->getJson("/api/enrollments/{$this->classmateEnrollment->id}/progress")
            ->assertForbidden();
    }

    public function test_the_academy_owner_can_still_read_a_learners_progress(): void
    {
        $this->in($this->bootcampsOwner)
            ->getJson("/api/enrollments/{$this->classmateEnrollment->id}/progress")
            ->assertOk();
    }

    public function test_a_teacher_who_is_only_a_learner_here_cannot_delete_this_academys_product(): void
    {
        $this->in($this->teacherFromElsewhere)->deleteJson("/api/products/{$this->product->id}")->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'deleted_at' => null]);
    }

    public function test_a_teacher_who_is_only_a_learner_here_cannot_deactivate_this_academys_product(): void
    {
        $this->in($this->teacherFromElsewhere)->postJson("/api/products/{$this->product->id}/deactivate")->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'status' => 'active']);
    }

    public function test_the_academy_owner_can_still_deactivate_their_product(): void
    {
        $this->in($this->bootcampsOwner)->postJson("/api/products/{$this->product->id}/deactivate")->assertOk();
    }

    public function test_a_teacher_who_is_only_a_learner_here_cannot_read_a_classmates_order(): void
    {
        $order = Order::create([
            'user_id' => $this->classmate->id, 'environment_id' => $this->bootcamps->id, 'order_number' => 'ORD-1',
            'status' => 'completed', 'total_amount' => 50000, 'currency' => 'XAF',
            'billing_name' => 'Classmate', 'billing_email' => 'classmate@example.com',
        ]);

        $this->in($this->teacherFromElsewhere)->getJson("/api/orders/{$order->id}")->assertForbidden();
    }

    // -------------------------------------- unscoped records: no teacher reaches another's

    public function test_a_teacher_cannot_delete_someone_elses_referral(): void
    {
        $referrer = User::factory()->create(['role' => 'sales_agent']);
        $referral = Referral::create(['referrer_id' => $referrer->id, 'code' => 'REF1', 'discount_type' => 'percentage', 'discount_value' => 10, 'is_active' => true]);

        $this->in($this->bootcampsOwner)->deleteJson("/api/sales/admin/referrals/{$referral->id}")->assertForbidden();

        $this->assertDatabaseHas('referrals', ['id' => $referral->id]);
    }

    public function test_a_teacher_cannot_read_a_quiz_submission_from_another_academy(): void
    {
        $otherOwner = User::factory()->create(['role' => 'company_teacher']);
        $other = Environment::create(['name' => 'Other', 'primary_domain' => 'other.example.com', 'slug' => 'other', 'owner_id' => $otherOwner->id]);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollmentId = DB::table('enrollments')->insertGetId([
            'user_id' => $learner->id, 'course_id' => $this->classmateEnrollment->course_id, 'environment_id' => $other->id,
            'status' => Enrollment::STATUS_ENROLLED, 'progress_percentage' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $submissionId = DB::table('quiz_submissions')->insertGetId([
            'quiz_content_id' => 1, 'user_id' => $learner->id, 'enrollment_id' => $enrollmentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // BootCamps' owner is a teacher — in BootCamps, not in Other.
        $this->in($this->bootcampsOwner)->getJson("/api/quiz/submissions/{$submissionId}/violations")->assertForbidden();
    }
}
