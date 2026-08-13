<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Setting a password is account takeover, so these endpoints must admit only
 * platform admins and the environment's own owner. They previously gated on a
 * helper that also admitted any teacher or sales agent — and since every
 * environment owner is a teacher, any tenant could take over any other.
 */
class PasswordSetAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function environmentOwnedBy(User $owner): Environment
    {
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        $owner->environments()->attach($environment->id, ['role' => 'owner']);

        return $environment;
    }

    private function userWithRole(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_a_teacher_from_another_environment_cannot_set_an_owner_password(): void
    {
        $victimOwner = $this->userWithRole(UserRole::INDIVIDUAL_TEACHER);
        $victimEnvironment = $this->environmentOwnedBy($victimOwner);
        $originalHash = $victimOwner->password;

        $attacker = $this->userWithRole(UserRole::INDIVIDUAL_TEACHER);
        $this->environmentOwnedBy($attacker);

        Sanctum::actingAs($attacker);

        $this->putJson("/api/environments/{$victimEnvironment->id}/owner-password", [
            'password' => 'attacker-chosen-password',
        ])->assertForbidden();

        $this->assertSame($originalHash, $victimOwner->fresh()->password);
    }

    public function test_a_sales_agent_cannot_set_an_owner_password(): void
    {
        $owner = $this->userWithRole(UserRole::INDIVIDUAL_TEACHER);
        $environment = $this->environmentOwnedBy($owner);
        $originalHash = $owner->password;

        Sanctum::actingAs($this->userWithRole(UserRole::SALES_AGENT));

        $this->putJson("/api/environments/{$environment->id}/owner-password", [
            'password' => 'agent-chosen-password',
        ])->assertForbidden();

        $this->assertSame($originalHash, $owner->fresh()->password);
    }

    public function test_a_platform_admin_can_set_an_owner_password(): void
    {
        $owner = $this->userWithRole(UserRole::INDIVIDUAL_TEACHER);
        $environment = $this->environmentOwnedBy($owner);

        Sanctum::actingAs($this->userWithRole(UserRole::ADMIN));

        $this->putJson("/api/environments/{$environment->id}/owner-password", [
            'password' => 'admin-chosen-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('admin-chosen-password', $owner->fresh()->password));
    }

    public function test_an_owner_can_set_their_own_environment_owner_password(): void
    {
        $owner = $this->userWithRole(UserRole::INDIVIDUAL_TEACHER);
        $environment = $this->environmentOwnedBy($owner);

        Sanctum::actingAs($owner);

        $this->putJson("/api/environments/{$environment->id}/owner-password", [
            'password' => 'owner-chosen-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('owner-chosen-password', $owner->fresh()->password));
    }

    public function test_a_teacher_from_another_environment_cannot_set_a_member_password(): void
    {
        $victimOwner = $this->userWithRole(UserRole::INDIVIDUAL_TEACHER);
        $victimEnvironment = $this->environmentOwnedBy($victimOwner);

        $member = $this->userWithRole(UserRole::LEARNER);
        $member->environments()->attach($victimEnvironment->id, ['role' => 'learner']);
        $originalHash = $member->password;

        $attacker = $this->userWithRole(UserRole::INDIVIDUAL_TEACHER);
        $this->environmentOwnedBy($attacker);

        Sanctum::actingAs($attacker);

        $this->putJson("/api/environments/{$victimEnvironment->id}/users/{$member->id}/password", [
            'password' => 'attacker-chosen-password',
        ])->assertForbidden();

        $this->assertSame($originalHash, $member->fresh()->password);
    }

    public function test_an_owner_can_set_a_member_password_in_their_own_environment(): void
    {
        $owner = $this->userWithRole(UserRole::INDIVIDUAL_TEACHER);
        $environment = $this->environmentOwnedBy($owner);

        $member = $this->userWithRole(UserRole::LEARNER);
        $member->environments()->attach($environment->id, ['role' => 'learner']);

        Sanctum::actingAs($owner);

        $this->putJson("/api/environments/{$environment->id}/users/{$member->id}/password", [
            'password' => 'owner-chosen-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('owner-chosen-password', $member->fresh()->password));
    }

    public function test_both_endpoints_reject_unauthenticated_callers(): void
    {
        $owner = $this->userWithRole(UserRole::INDIVIDUAL_TEACHER);
        $environment = $this->environmentOwnedBy($owner);

        $this->putJson("/api/environments/{$environment->id}/owner-password", ['password' => 'x'])
            ->assertUnauthorized();

        $this->putJson("/api/environments/{$environment->id}/users/{$owner->id}/password", ['password' => 'x'])
            ->assertUnauthorized();
    }
}
