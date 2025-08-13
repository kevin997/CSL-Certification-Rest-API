<?php

namespace Tests\Feature\Api;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TemplateApiTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:install');
    }

    /** @test */
    public function it_can_create_a_template_with_template_code()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $templateData = [
            'title' => $this->faker->sentence,
            'template_code' => 'TMPL-101',
            'description' => $this->faker->paragraph,
            'is_public' => true,
        ];

        $response = $this->postJson('/api/templates', $templateData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'title' => $templateData['title'],
                'template_code' => $templateData['template_code'],
            ]);

        $this->assertDatabaseHas('templates', [
            'title' => $templateData['title'],
            'template_code' => $templateData['template_code'],
        ]);
    }

    /** @test */
    public function it_can_update_a_template_with_template_code()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $template = Template::factory()->create([
            'created_by' => $user->id,
        ]);

        $updateData = [
            'title' => $this->faker->sentence,
            'template_code' => 'TMPL-202',
            'description' => $this->faker->paragraph,
        ];

        $response = $this->putJson('/api/templates/' . $template->id, $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => $updateData['title'],
                'template_code' => $updateData['template_code'],
            ]);

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'title' => $updateData['title'],
            'template_code' => $updateData['template_code'],
        ]);
    }

    /** @test */
    public function template_code_is_optional_when_creating_template()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $templateData = [
            'title' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_public' => true,
        ];

        $response = $this->postJson('/api/templates', $templateData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('templates', [
            'title' => $templateData['title'],
            'template_code' => null,
        ]);
    }

    /** @test */
    public function template_code_cannot_exceed_50_characters()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $templateData = [
            'title' => $this->faker->sentence,
            'template_code' => str_repeat('A', 51), // 51 characters
            'description' => $this->faker->paragraph,
            'is_public' => true,
        ];

        $response = $this->postJson('/api/templates', $templateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['template_code']);
    }
}
