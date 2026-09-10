<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\EnrollmentCode;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Who may redeem an enrollment code is a question about the environment the
 * code is redeemed in, not about the account's role anywhere else.
 *
 * The endpoint used to refuse any existing account whose *global* role was not
 * learner. Owning an academy makes that role company_teacher, so a teacher
 * could never be a learner in somebody else's academy — even one they were
 * already a learner member of. Sign-in already answers this per environment
 * (see SessionAuthRoleConsistencyTest); redemption now agrees with it.
 */
class EnrollmentCodeRedemptionEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private Environment $academy;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['licensing.enforcement_enabled' => false]);

        $owner = User::factory()->create(['role' => 'company_teacher']);
        $this->academy = Environment::create([
            'name' => 'BootCamps',
            'primary_domain' => 'bootcamps.example.com',
            'slug' => 'bootcamps',
            'owner_id' => $owner->id,
        ]);

        $category = ProductCategory::create([
            'name' => 'Data', 'slug' => 'data',
            'environment_id' => $this->academy->id, 'created_by' => $owner->id,
        ]);
        $template = Template::create([
            'title' => 'Data Analysis', 'environment_id' => $this->academy->id, 'created_by' => $owner->id,
        ]);
        $course = Course::create([
            'title' => 'Data Analysis', 'slug' => 'data-analysis', 'description' => 'x',
            'environment_id' => $this->academy->id, 'created_by' => $owner->id, 'status' => 'published',
            'template_id' => $template->id,
        ]);
        $this->product = Product::create([
            'name' => 'Data Analysis Bootcamp', 'description' => 'x', 'price' => 50000,
            'currency' => 'XAF', 'is_subscription' => false, 'status' => 'active',
            'category_id' => $category->id, 'sku' => 'DA-001',
            'environment_id' => $this->academy->id, 'created_by' => $owner->id,
        ]);
        DB::table('product_courses')->insert([
            'product_id' => $this->product->id, 'course_id' => $course->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function code(string $code = 'AB12'): EnrollmentCode
    {
        return EnrollmentCode::create([
            'product_id' => $this->product->id,
            'code' => $code,
            'status' => 'active',
            'created_by' => $this->academy->owner_id,
        ]);
    }

    private function redeem(User $user, string $code = 'AB12')
    {
        // The SPA origin is what makes Sanctum start a session for this route;
        // redemption signs the account in, so without one it cannot run.
        return $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/redeem',
        ])->withSession(['current_environment_id' => $this->academy->id])
            ->postJson('/api/enrollment-codes/redeem-with-registration', [
                'code' => $code,
                'product_id' => $this->product->id,
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'password',
            ]);
    }

    private function teacherWithAnAcademyOfTheirOwn(): User
    {
        $teacher = User::factory()->create(['role' => 'company_teacher']);
        Environment::create([
            'name' => 'Satoshi School', 'primary_domain' => 'satoshi.example.com',
            'slug' => 'satoshi', 'owner_id' => $teacher->id,
        ]);

        return $teacher;
    }

    public function test_a_teacher_elsewhere_who_is_a_learner_here_can_redeem(): void
    {
        // The reported case: owns another academy, already a learner in this one.
        $teacher = $this->teacherWithAnAcademyOfTheirOwn();
        EnvironmentUser::create(['environment_id' => $this->academy->id, 'user_id' => $teacher->id, 'role' => 'learner']);
        $this->code();

        $this->redeem($teacher)->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('enrollments', ['user_id' => $teacher->id, 'environment_id' => $this->academy->id]);
    }

    public function test_a_teacher_elsewhere_with_no_membership_here_is_enrolled_as_a_learner_member(): void
    {
        // Enrolling without a membership would leave them unable to sign in to
        // the academy they just redeemed a code for.
        $teacher = $this->teacherWithAnAcademyOfTheirOwn();
        $this->code();

        $this->redeem($teacher)->assertCreated();

        $this->assertDatabaseHas('environment_user', [
            'environment_id' => $this->academy->id,
            'user_id' => $teacher->id,
            'role' => 'learner',
        ]);
    }

    public function test_the_owner_cannot_redeem_a_learner_code_in_their_own_academy(): void
    {
        $this->code();
        $owner = User::find($this->academy->owner_id);

        $this->redeem($owner)->assertForbidden();

        $this->assertDatabaseHas('enrollment_codes', ['code' => 'AB12', 'status' => 'active']);
    }

    public function test_staff_of_this_academy_cannot_redeem(): void
    {
        $staff = User::factory()->create(['role' => 'learner']);
        EnvironmentUser::create(['environment_id' => $this->academy->id, 'user_id' => $staff->id, 'role' => 'company_team_member']);
        $this->code();

        $this->redeem($staff)->assertForbidden();
    }

    public function test_a_platform_admin_cannot_redeem(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->code();

        $this->redeem($admin)->assertForbidden();
    }

    public function test_a_company_learner_is_a_learner_and_may_redeem(): void
    {
        // The first version of this fix treated any membership role other than
        // exactly 'learner' as staff, which would have refused company_learner —
        // a role that exists in production.
        $learner = User::factory()->create(['role' => 'learner']);
        EnvironmentUser::create(['environment_id' => $this->academy->id, 'user_id' => $learner->id, 'role' => 'company_learner']);
        $this->code();

        $this->redeem($learner)->assertCreated();
    }

    public function test_an_ordinary_learner_still_redeems(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $this->code();

        $this->redeem($learner)->assertCreated();
    }
}
