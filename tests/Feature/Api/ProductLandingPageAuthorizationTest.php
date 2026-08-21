<?php

namespace Tests\Feature\Api;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Managing a product's landing page requires more than being signed in.
 *
 * These endpoints used to resolve the product through EnvironmentScope alone.
 * That scope applies only when the session carries a current_environment_id,
 * so a bearer-token client on an unresolvable host got no filtering at all,
 * and even when it applied it matched rows with a null environment_id. There
 * was no role check either -- and learners are environment members
 * (`learner`, `company_learner`), so any signed-in learner could publish a
 * landing page on any product in their environment.
 */
class ProductLandingPageAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function product(Environment $environment): Product
    {
        return Product::withoutGlobalScopes()->create([
            'name' => 'Pro Course',
            'slug' => 'pro-course-'.$environment->id,
            'price' => 49.00,
            'status' => 'active',
            'environment_id' => $environment->id,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    private function member(Environment $environment, string $role): User
    {
        $user = User::factory()->create();

        EnvironmentUser::create([
            'environment_id' => $environment->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }

    public function test_a_learner_in_the_environment_cannot_publish_a_landing_page(): void
    {
        $environment = Environment::factory()->create();
        $product = $this->product($environment);
        $learner = $this->member($environment, 'learner');

        // No session environment: this is the case the old scope did not cover.
        $this->actingAs($learner)
            ->postJson("/api/products/{$product->id}/landing-page/toggle", ['enabled' => true])
            ->assertForbidden();

        $this->assertDatabaseCount('product_landing_pages', 0);
    }

    public function test_an_environment_owner_may_manage_the_page(): void
    {
        $environment = Environment::factory()->create();
        $product = $this->product($environment);
        $owner = $this->member($environment, 'owner');

        $this->actingAs($owner)
            ->getJson("/api/products/{$product->id}/landing-page")
            ->assertOk();
    }

    public function test_a_team_member_may_manage_the_page(): void
    {
        $environment = Environment::factory()->create();
        $product = $this->product($environment);
        $teamMember = $this->member($environment, 'company_team_member');

        $this->actingAs($teamMember)
            ->getJson("/api/products/{$product->id}/landing-page")
            ->assertOk();
    }

    public function test_the_environment_owner_by_owner_id_may_manage_the_page(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        $product = $this->product($environment);

        // Owner by environments.owner_id, with no environment_user row at all.
        $this->actingAs($owner)
            ->getJson("/api/products/{$product->id}/landing-page")
            ->assertOk();
    }

    public function test_a_member_of_another_environment_gets_a_not_found(): void
    {
        $mine = Environment::factory()->create();
        $theirs = Environment::factory()->create();
        $product = $this->product($theirs);
        $outsider = $this->member($mine, 'owner');

        // Not 403: confirming the product exists would tell an outsider what
        // another tenant owns.
        $this->actingAs($outsider)
            ->getJson("/api/products/{$product->id}/landing-page")
            ->assertNotFound();
    }

    public function test_a_signed_in_user_with_no_membership_gets_a_not_found(): void
    {
        $environment = Environment::factory()->create();
        $product = $this->product($environment);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->putJson("/api/products/{$product->id}/landing-page", ['seo_title' => 'Mine now'])
            ->assertNotFound();

        $this->assertDatabaseCount('product_landing_pages', 0);
    }
}
