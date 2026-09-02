<?php

namespace Tests\Feature\Tenancy;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The probe only ever sets domain liveness, so clearing it is an operator
 * action — and it decides which host every link for that academy points at.
 */
class DomainVerificationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_mark_a_domain_live_and_clear_it_again(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $environment = Environment::factory()->create();

        $this->actingAs($admin)
            ->putJson("/api/admin/environments/{$environment->id}/domain-verification", ['verified' => true])
            ->assertOk()
            ->assertJsonPath('data.id', $environment->id);
        $this->assertNotNull($environment->fresh()->domain_verified_at);

        $this->actingAs($admin)
            ->putJson("/api/admin/environments/{$environment->id}/domain-verification", ['verified' => false])
            ->assertOk()
            ->assertJsonPath('data.domain_verified_at', null);
        $this->assertNull($environment->fresh()->domain_verified_at);
    }

    public function test_a_teacher_cannot_touch_domain_verification(): void
    {
        $teacher = User::factory()->create(['role' => 'company_teacher']);
        $environment = Environment::factory()->create(['owner_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->putJson("/api/admin/environments/{$environment->id}/domain-verification", ['verified' => true])
            ->assertForbidden();
    }
}
