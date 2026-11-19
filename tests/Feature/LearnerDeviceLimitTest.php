<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\LearnerDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearnerDeviceLimitTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private User $learner;

    private User $instructor;

    private string $domain = 'academy-devices.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'devices.enforced' => true,
            'devices.max_active' => 2,
            'devices.idle_expiry_days' => 180,
        ]);

        $this->instructor = User::factory()->create([
            'role' => UserRole::INDIVIDUAL_TEACHER->value,
        ]);

        $this->environment = Environment::factory()->create([
            'owner_id' => $this->instructor->id,
            'primary_domain' => $this->domain,
            'domain_verified_at' => now(),
        ]);

        $this->learner = User::factory()->create([
            'role' => UserRole::LEARNER->value,
        ]);

        EnvironmentUser::create([
            'environment_id' => $this->environment->id,
            'user_id' => $this->learner->id,
            'role' => UserRole::LEARNER->value,
            'is_account_setup' => true,
        ]);
    }

    public function test_a_learner_can_use_two_devices_and_a_third_is_refused(): void
    {
        $this->assertSame(200, $this->asLearner(Str::uuid())->getJson('/api/learner/courses')->status());
        $this->assertSame(200, $this->asLearner(Str::uuid())->getJson('/api/learner/courses')->status());

        $response = $this->asLearner(Str::uuid())->getJson('/api/learner/courses');

        $response->assertStatus(403)->assertJson(['code' => 'device_limit_reached', 'max_devices' => 2]);

        $this->assertSame(2, LearnerDevice::query()->active()->count());
    }

    public function test_the_same_device_does_not_consume_a_second_slot(): void
    {
        $uuid = Str::uuid();

        $this->asLearner($uuid)->getJson('/api/learner/courses');
        $this->asLearner($uuid)->getJson('/api/learner/courses');
        $this->asLearner($uuid)->getJson('/api/learner/courses');

        $this->assertSame(1, LearnerDevice::query()->active()->count());
    }

    public function test_an_instructor_revokes_a_device_and_it_is_refused_immediately(): void
    {
        $first = Str::uuid();
        $this->asLearner($first)->getJson('/api/learner/courses');

        $device = LearnerDevice::query()->firstOrFail();

        $this->actingAs($this->instructor, 'sanctum')
            ->withHeader('X-Frontend-Domain', $this->domain)
            ->deleteJson("/api/learners/{$this->learner->id}/devices/{$device->id}")
            ->assertOk()
            ->assertJsonPath('meta.max_devices', 2);

        $this->asLearner($first)->getJson('/api/learner/courses')
            ->assertStatus(401)
            ->assertJson(['code' => 'device_revoked']);

        $this->assertSame(1, LearnerDevice::query()->where('status', LearnerDevice::STATUS_REVOKED)->count());
    }

    public function test_the_instructor_sees_the_devices_of_a_learner_in_their_academy(): void
    {
        $this->asLearner(Str::uuid())->getJson('/api/learner/courses');

        $this->actingAs($this->instructor, 'sanctum')
            ->withHeader('X-Frontend-Domain', $this->domain)
            ->getJson("/api/learners/{$this->learner->id}/devices")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_type', 'desktop')
            ->assertJsonPath('meta.active_devices', 1)
            ->assertJsonPath('meta.max_devices', 2);
    }

    public function test_an_instructor_from_another_academy_cannot_manage_the_learner(): void
    {
        $outsider = User::factory()->create([
            'role' => UserRole::INDIVIDUAL_TEACHER->value,
        ]);

        $other = Environment::factory()->create([
            'owner_id' => $outsider->id,
            'primary_domain' => 'other-devices.test',
            'domain_verified_at' => now(),
        ]);

        EnvironmentUser::create([
            'environment_id' => $other->id,
            'user_id' => $outsider->id,
            'role' => UserRole::INDIVIDUAL_TEACHER->value,
            'is_account_setup' => true,
        ]);

        $this->actingAs($outsider, 'sanctum')
            ->withHeader('X-Frontend-Domain', $other->primary_domain)
            ->getJson("/api/learners/{$this->learner->id}/devices")
            ->assertNotFound();
    }

    public function test_staff_do_not_consume_a_learner_slot(): void
    {
        $this->actingAs($this->instructor, 'sanctum')
            ->withHeaders([
                'X-Frontend-Domain' => $this->domain,
                'X-Device-Id' => Str::uuid(),
            ])
            ->getJson('/api/learner/courses');

        $this->assertSame(0, LearnerDevice::query()->count());
    }

    private function asLearner(string $uuid): static
    {
        return $this->actingAs($this->learner, 'sanctum')
            ->withHeaders([
                'X-Frontend-Domain' => $this->domain,
                'X-Device-Id' => $uuid,
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
            ]);
    }
}
