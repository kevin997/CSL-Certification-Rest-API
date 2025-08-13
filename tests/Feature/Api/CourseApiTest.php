<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseApiTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:install');
    }

    /** @test */
    public function it_can_create_a_course_with_course_code()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $template = Template::factory()->create([
            'created_by' => $user->id
        ]);

        $courseData = [
            'title' => $this->faker->sentence,
            'course_code' => 'CSL-101',
            'description' => $this->faker->paragraph,
            'template_id' => $template->id,
            'status' => 'draft',
        ];

        $response = $this->postJson('/api/courses', $courseData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'title' => $courseData['title'],
                'course_code' => $courseData['course_code'],
            ]);

        $this->assertDatabaseHas('courses', [
            'title' => $courseData['title'],
            'course_code' => $courseData['course_code'],
        ]);
    }

    /** @test */
    public function it_can_update_a_course_with_course_code()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $template = Template::factory()->create([
            'created_by' => $user->id
        ]);

        $course = Course::factory()->create([
            'created_by' => $user->id,
            'template_id' => $template->id,
        ]);

        $updateData = [
            'title' => $this->faker->sentence,
            'course_code' => 'CSL-202',
            'description' => $this->faker->paragraph,
        ];

        $response = $this->putJson('/api/courses/' . $course->id, $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => $updateData['title'],
                'course_code' => $updateData['course_code'],
            ]);

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => $updateData['title'],
            'course_code' => $updateData['course_code'],
        ]);
    }

    /** @test */
    public function course_code_is_optional_when_creating_course()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $template = Template::factory()->create([
            'created_by' => $user->id
        ]);

        $courseData = [
            'title' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'template_id' => $template->id,
            'status' => 'draft',
        ];

        $response = $this->postJson('/api/courses', $courseData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('courses', [
            'title' => $courseData['title'],
            'course_code' => null,
        ]);
    }

    /** @test */
    public function course_code_cannot_exceed_50_characters()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $template = Template::factory()->create([
            'created_by' => $user->id
        ]);

        $courseData = [
            'title' => $this->faker->sentence,
            'course_code' => str_repeat('A', 51), // 51 characters
            'description' => $this->faker->paragraph,
            'template_id' => $template->id,
            'status' => 'draft',
        ];

        $response = $this->postJson('/api/courses', $courseData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['course_code']);
    }
}
