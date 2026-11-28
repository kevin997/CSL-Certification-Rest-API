<?php

namespace Tests\Feature\Tenancy;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\SalesForm;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademyActivityTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/notifications/academy-activity';

    private Environment $academy;

    private Environment $other;

    private User $owner;

    private User $teacherElsewhere;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'company_teacher']);
        $this->teacherElsewhere = User::factory()->create(['role' => 'company_teacher']);
        $this->staff = User::factory()->create(['role' => 'learner']);
        $this->academy = Environment::create([
            'name' => 'Sample Academy', 'slug' => 'sample-academy', 'primary_domain' => 'sample.example.com',
            'owner_id' => $this->owner->id, 'is_active' => true,
        ]);
        $this->other = Environment::create([
            'name' => 'Other Academy', 'slug' => 'other-academy', 'primary_domain' => 'other.example.com',
            'owner_id' => $this->teacherElsewhere->id, 'is_active' => true,
        ]);
        EnvironmentUser::create([
            'environment_id' => $this->academy->id, 'user_id' => $this->teacherElsewhere->id, 'role' => 'learner',
        ]);
        EnvironmentUser::create([
            'environment_id' => $this->academy->id, 'user_id' => $this->staff->id, 'role' => 'instructor',
        ]);

        $this->withHeader('X-Frontend-Domain', $this->academy->primary_domain);
    }

    private function order(Environment $environment, array $overrides = []): int
    {
        return DB::table('orders')->insertGetId(array_merge([
            'environment_id' => $environment->id, 'user_id' => $this->teacherElsewhere->id,
            'order_number' => fake()->unique()->bothify('ORDER-######'),
            'status' => 'pending', 'currency' => 'XAF', 'total_amount' => 20000,
            'billing_name' => 'Private buyer name', 'billing_email' => 'private@example.com',
            'payment_id' => 'PRIVATE-REFERENCE', 'created_at' => now()->subHour(), 'updated_at' => now(),
        ], $overrides));
    }

    private function course(Environment $environment): Course
    {
        $template = Template::create([
            'title' => 'Template', 'environment_id' => $environment->id, 'created_by' => $environment->owner_id,
        ]);

        return Course::create([
            'title' => 'Media Buying', 'description' => 'Example description', 'slug' => fake()->unique()->slug(),
            'status' => 'published', 'environment_id' => $environment->id, 'template_id' => $template->id,
            'created_by' => $environment->owner_id,
        ]);
    }

    private function enrollment(Environment $environment, Course $course, array $overrides = []): int
    {
        return DB::table('enrollments')->insertGetId(array_merge([
            'environment_id' => $environment->id, 'course_id' => $course->id, 'user_id' => $this->teacherElsewhere->id,
            'status' => Enrollment::STATUS_ENROLLED, 'is_provisional' => false,
            'created_at' => now()->subHours(2), 'updated_at' => now(),
        ], $overrides));
    }

    private function form(Environment $environment): SalesForm
    {
        return SalesForm::create([
            'environment_id' => $environment->id, 'created_by' => $environment->owner_id,
            'title' => 'Course application', 'slug' => fake()->unique()->slug(), 'status' => SalesForm::STATUS_PUBLISHED,
        ]);
    }

    private function submission(Environment $environment, SalesForm $form): int
    {
        return DB::table('sales_form_submissions')->insertGetId([
            'environment_id' => $environment->id, 'sales_form_id' => $form->id,
            'access_code' => 'PRIVATE-CODE', 'answers' => json_encode(['answer' => 'PRIVATE-ANSWER']),
            'name' => 'Private applicant', 'phone' => '+237600000000', 'email' => 'applicant@example.com',
            'created_at' => now()->subMinutes(35), 'updated_at' => now(),
        ]);
    }

    public function test_it_requires_authentication_and_role_in_the_active_academy(): void
    {
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
        $this->actingAs($this->teacherElsewhere)->getJson(self::ENDPOINT)->assertForbidden();
        $this->actingAs($this->staff)->getJson(self::ENDPOINT)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_it_merges_existing_records_without_exposing_private_fields_or_modifying_notifications(): void
    {
        $orderId = $this->order($this->academy);
        $enrollmentId = $this->enrollment($this->academy, $this->course($this->academy));
        $formId = $this->submission($this->academy, $this->form($this->academy));

        $response = $this->actingAs($this->owner)->getJson(self::ENDPOINT)
            ->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', 'form_submission:'.$formId)
            ->assertJsonPath('data.0.context_name', 'Course application')
            ->assertJsonPath('data.1.id', 'order:'.$orderId)
            ->assertJsonPath('data.1.context_name', null)
            ->assertJsonPath('data.2.id', 'enrollment:'.$enrollmentId)
            ->assertJsonPath('data.2.context_name', 'Media Buying')
            ->assertJsonPath('meta.limit', 15)->assertJsonPath('meta.lookback_days', 14)
            ->assertJsonPath('meta.truncated', false);

        foreach ($response->json('data') as $row) {
            $this->assertSame(['id', 'type', 'occurred_at', 'resource_id', 'context_name'], array_keys($row));
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('user_notifications', 0);
        $this->getJson('/api/environments/'.$this->academy->id.'/notifications/unread-count')
            ->assertOk()->assertJsonPath('data.count', 0);
    }

    public function test_foreign_academy_and_mismatched_linked_records_are_not_included(): void
    {
        $ownOrderId = $this->order($this->academy);
        $this->order($this->other);
        $otherCourse = $this->course($this->other);
        $this->enrollment($this->other, $otherCourse);
        $this->enrollment($this->academy, $otherCourse, ['user_id' => $this->staff->id]);
        $otherForm = $this->form($this->other);
        $this->submission($this->other, $otherForm);
        $this->submission($this->academy, $otherForm);

        $this->actingAs($this->owner)
            ->getJson(self::ENDPOINT.'?environment_id='.$this->other->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 'order:'.$ownOrderId);
        $this->withHeader('X-Frontend-Domain', $this->other->primary_domain)
            ->getJson(self::ENDPOINT)->assertForbidden();
    }

    public function test_it_excludes_outside_window_deleted_and_provisional_rows_and_validates_limits(): void
    {
        $olderId = $this->order($this->academy);
        $newerId = $this->order($this->academy);
        $this->order($this->academy, ['created_at' => now()->subDays(15)]);
        $this->order($this->academy, ['created_at' => now()->addMinute()]);
        $this->order($this->academy, ['deleted_at' => now()]);
        $this->enrollment($this->academy, $this->course($this->academy), ['is_provisional' => true]);

        $this->actingAs($this->owner)->getJson(self::ENDPOINT.'?limit=0')->assertUnprocessable();
        $this->getJson(self::ENDPOINT.'?limit=26')->assertUnprocessable();
        $this->getJson(self::ENDPOINT.'?limit=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'order:'.$newerId)->assertJsonPath('meta.truncated', true);
        $this->getJson(self::ENDPOINT.'?limit=2')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'order:'.$newerId)
            ->assertJsonPath('data.1.id', 'order:'.$olderId)
            ->assertJsonPath('meta.truncated', false);
    }
}
