<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\IntegrationInterest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationInterestTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->environment = Environment::factory()->create(['owner_id' => $this->user->id]);
    }

    public function test_user_can_register_interest_and_it_is_idempotent(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/integration-interests', [
                'integration_id' => 'zapier',
                'environment_id' => $this->environment->id,
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        // Same registration again — no duplicate
        $this->actingAs($this->user)
            ->postJson('/api/integration-interests', [
                'integration_id' => 'zapier',
                'environment_id' => $this->environment->id,
            ])
            ->assertStatus(201);

        $this->assertSame(1, IntegrationInterest::withoutGlobalScopes()->count());
    }

    public function test_index_returns_only_current_users_interests(): void
    {
        IntegrationInterest::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'user_id' => $this->user->id,
            'integration_id' => 'zoom',
        ]);
        $other = User::factory()->create();
        IntegrationInterest::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'user_id' => $other->id,
            'integration_id' => 'slack',
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/integration-interests?environment_id=' . $this->environment->id)
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['zoom']]);
    }

    public function test_integration_id_is_required(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/integration-interests', ['environment_id' => $this->environment->id])
            ->assertStatus(422);
    }
}
