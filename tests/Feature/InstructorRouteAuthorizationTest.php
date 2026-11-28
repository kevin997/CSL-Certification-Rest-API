<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DashboardController;
use App\Http\Middleware\EnsureAcademyStaff;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class InstructorRouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'academy.example.test';

    private function academy(User $owner): Environment
    {
        return Environment::factory()->create([
            'owner_id' => $owner->id,
            'primary_domain' => self::HOST,
            'domain_verified_at' => now(),
        ]);
    }

    private function member(Environment $academy, User $user, string $role): void
    {
        EnvironmentUser::query()->create([
            'environment_id' => $academy->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    public function test_plain_learner_cannot_load_academy_staff_data(): void
    {
        $academy = $this->academy(User::factory()->create(['role' => 'company_teacher']));
        $learner = User::factory()->create(['role' => 'learner']);
        $this->member($academy, $learner, 'learner');
        $this->actingAs($learner)->withSession(['current_environment_id' => $academy->id]);

        foreach ([
            '/dashboard',
            '/analytics/overview',
            '/analytics/environment/overview',
            '/analytics/financial-widgets',
            '/analytics/traffic-widgets',
            '/instructor/earnings/stats',
            '/chat/instructor/discussions',
            '/sales-forms',
            '/orders',
            '/enrollments',
            '/learners/'.$learner->id,
            '/learners/'.$learner->id.'/enrollments/1/submissions',
            '/finance/overview',
        ] as $path) {
            $this->getJson('https://'.self::HOST.'/api'.$path)
                ->assertForbidden()
                ->assertJsonPath('code', 'academy_staff_required')
                ->assertJsonMissingPath('data');
        }
    }

    public function test_global_teacher_with_learner_membership_cannot_use_staff_dashboard_here(): void
    {
        $academy = $this->academy(User::factory()->create(['role' => 'company_teacher']));
        $visitingTeacher = User::factory()->create(['role' => 'company_teacher']);
        Environment::factory()->create(['owner_id' => $visitingTeacher->id]);
        $this->member($academy, $visitingTeacher, 'learner');

        $this->actingAs($visitingTeacher)->withSession(['current_environment_id' => $academy->id])
            ->getJson('https://'.self::HOST.'/api/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'academy_staff_required');
    }

    public function test_staff_lookup_cannot_read_a_profile_outside_their_academy(): void
    {
        $owner = User::factory()->create(['role' => 'company_teacher']);
        $academy = $this->academy($owner);
        $local = User::factory()->create(['role' => 'learner']);
        $outside = User::factory()->create(['role' => 'learner']);
        $this->member($academy, $local, 'learner');
        $this->actingAs($owner)->withSession(['current_environment_id' => $academy->id]);

        $this->getJson('https://'.self::HOST.'/api/learners/'.$local->id)->assertOk();
        $this->getJson('https://'.self::HOST.'/api/learners/'.$outside->id)->assertNotFound();
    }

    public function test_owner_and_instructor_use_the_authorized_academy_context(): void
    {
        $owner = User::factory()->create(['role' => 'company_teacher']);
        $academy = $this->academy($owner);
        $instructor = User::factory()->create(['role' => 'learner']);
        $this->member($academy, $instructor, 'instructor');

        $this->mock(DashboardController::class, function ($mock) use ($academy) {
            $mock->shouldReceive('getDashboardData')->twice()->andReturnUsing(function (Request $request) use ($academy) {
                $this->assertSame($academy->id, $request->attributes->get(EnsureAcademyStaff::ACADEMY_ID));

                return response()->json(['success' => true, 'data' => ['authorizedFixture' => true]]);
            });
        });

        foreach ([$owner, $instructor] as $staff) {
            $this->actingAs($staff)->withSession(['current_environment_id' => $academy->id])
                ->getJson('https://'.self::HOST.'/api/dashboard')
                ->assertOk()->assertJsonPath('data.authorizedFixture', true);
        }
    }
}
