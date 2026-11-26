<?php

namespace Tests\Feature\Marketing;

use App\Enums\UserRole;
use App\Models\Environment;
use App\Models\SalesForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The bonuses section is stored inside the form settings payload and must
 * survive the builder save and reach the public form exactly as authored.
 */
class SalesFormBonusesTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private User $owner;

    private string $domain = 'academy-bonuses.test';

    private SalesForm $form;

    protected function setUp(): void
    {
        parent::setUp();

        config(['licensing.enforcement_enabled' => false]);

        $this->owner = User::factory()->create([
            'role' => UserRole::INDIVIDUAL_TEACHER->value,
        ]);

        $this->environment = Environment::factory()->create([
            'owner_id' => $this->owner->id,
            'primary_domain' => $this->domain,
            'domain_verified_at' => now(),
        ]);

        $this->form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'created_by' => $this->owner->id,
            'title' => 'Bonuses form',
            'slug' => 'bonuses-form',
            'status' => SalesForm::STATUS_DRAFT,
        ]);
    }

    public function test_the_builder_saves_and_returns_the_bonuses_section(): void
    {
        $bonuses = $this->bonusesPayload();

        $this->asOwner()
            ->putJson("/api/sales-forms/{$this->form->id}", [
                'title' => 'Bonuses form',
                'settings' => ['bonuses' => $bonuses],
            ])
            ->assertOk();

        $response = $this->asOwner()->getJson("/api/sales-forms/{$this->form->id}");

        $response->assertOk();
        $response->assertJsonPath('data.settings.bonuses.enabled', true);
        $response->assertJsonPath('data.settings.bonuses.title', 'What you get');
        $response->assertJsonPath('data.settings.bonuses.items.0.title', '<p><strong>Bonus 1</strong>: Templates</p>');
        $response->assertJsonPath('data.settings.bonuses.items.0.subtitle', '<p>Worth $200</p>');
        $response->assertJsonPath('data.settings.bonuses.items.0.description', '<p>Ready-to-use <em>course templates</em>.</p>');
        $response->assertJsonPath('data.settings.bonuses.items.1.title', '<p>Bonus 2</p>');
    }

    public function test_the_public_form_payload_contains_the_bonuses_section(): void
    {
        $this->form->update([
            'status' => SalesForm::STATUS_PUBLISHED,
            'settings' => ['bonuses' => $this->bonusesPayload()],
        ]);

        $response = $this->getJson('/api/sales-forms/public/bonuses-form');

        $response->assertOk();
        $response->assertJsonPath('data.settings.bonuses.items.0.title', '<p><strong>Bonus 1</strong>: Templates</p>');
        $response->assertJsonPath('data.settings.bonuses.items.1.description', '<p>Second bonus.</p>');
    }

    public function test_an_empty_bonuses_section_is_allowed(): void
    {
        $this->asOwner()
            ->putJson("/api/sales-forms/{$this->form->id}", [
                'title' => 'Bonuses form',
                'settings' => ['bonuses' => ['enabled' => false, 'title' => '', 'items' => []]],
            ])
            ->assertOk();

        // ConvertEmptyStringsToNull normalises the empty section title.
        $this->assertSame(
            ['enabled' => false, 'title' => null, 'items' => []],
            $this->form->fresh()->settings['bonuses'],
        );
    }

    public function test_bonuses_payload_is_validated(): void
    {
        $this->asOwner()
            ->putJson("/api/sales-forms/{$this->form->id}", [
                'title' => 'Bonuses form',
                'settings' => ['bonuses' => ['enabled' => 'not-a-boolean', 'items' => 'nope']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'settings.bonuses.enabled',
                'settings.bonuses.items',
            ]);
    }

    public function test_more_than_twenty_bonuses_are_rejected(): void
    {
        $items = array_fill(0, 21, ['title' => '<p>Bonus</p>', 'subtitle' => '', 'description' => '']);

        $this->asOwner()
            ->putJson("/api/sales-forms/{$this->form->id}", [
                'title' => 'Bonuses form',
                'settings' => ['bonuses' => ['enabled' => true, 'items' => $items]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.bonuses.items']);
    }

    /**
     * @return array{enabled: bool, title: string, items: array<int, array<string, string>>}
     */
    private function bonusesPayload(): array
    {
        return [
            'enabled' => true,
            'title' => 'What you get',
            'items' => [
                [
                    'title' => '<p><strong>Bonus 1</strong>: Templates</p>',
                    'subtitle' => '<p>Worth $200</p>',
                    'description' => '<p>Ready-to-use <em>course templates</em>.</p>',
                ],
                [
                    'title' => '<p>Bonus 2</p>',
                    'subtitle' => '<p>Private community</p>',
                    'description' => '<p>Second bonus.</p>',
                ],
            ],
        ];
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner, 'sanctum')->withHeaders([
            'X-Frontend-Domain' => $this->domain,
            'X-Device-Id' => Str::uuid(),
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
        ]);
    }
}
