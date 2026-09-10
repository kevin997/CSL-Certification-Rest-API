<?php

namespace Tests\Feature\Retention;

use App\Models\RetentionMessage;
use App\Support\Retention\RetentionCopywriter;
use App\Support\Retention\RetentionScenario;
use App\Support\Retention\RetentionScenarioRegistry;
use App\Support\Retention\RetentionTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The copywriter replaces a template that says the same sentence to everyone.
 * Nothing reviews its output before it reaches a phone, so every test here
 * states what reaches the customer if the guard it exercises is removed.
 */
class RetentionCopywriterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.retention.locales' => ['fr', 'en'],
            'ai.providers.deepseek.key' => 'test-deepseek-key',
            'ai.providers.openrouter.key' => 'test-openrouter-key',
        ]);
    }

    private function scenario(bool $personalised = true): RetentionScenario
    {
        return new RetentionScenario(
            'learner_stalled',
            RetentionScenario::LEARNER,
            75,
            7,
            fn () => collect(),
            'retention.learner_stalled',
            personalised: $personalised,
        );
    }

    private function target(): RetentionTarget
    {
        return new RetentionTarget('learner', '4242', '+237600000000', 'a@b.test', 'Awa Ngono', null, [
            'course' => 'Media Buying',
            'progress' => 40,
            'environment_id' => 1,
        ]);
    }

    private function writer(\Closure $generator): RetentionCopywriter
    {
        return new RetentionCopywriter($generator);
    }

    public function test_a_usable_message_replaces_the_template(): void
    {
        $writer = $this->writer(fn () => ['fr' => 'Awa, il te reste 60% de Media Buying.', 'en' => 'Awa, 60% of Media Buying is still waiting.']);

        $message = $writer->write($this->scenario(), $this->target());

        $this->assertSame(
            "Awa, il te reste 60% de Media Buying.\n\nAwa, 60% of Media Buying is still waiting.",
            $message
        );
    }

    public function test_a_scenario_that_did_not_opt_in_is_never_charged_a_model_call(): void
    {
        // Counted rather than asserted inside the closure: write() catches
        // Throwable, so a failed assertion in there would be swallowed and the
        // test would pass with the guard removed.
        $calls = 0;
        $writer = $this->writer(function () use (&$calls): array {
            $calls++;

            return ['fr' => 'Bonjour', 'en' => 'Hello'];
        });

        $this->assertNull($writer->write($this->scenario(personalised: false), $this->target()));
        $this->assertSame(0, $calls, 'an opted-out scenario must not reach the model');
    }

    public function test_an_unconfigured_provider_costs_nothing_and_falls_back(): void
    {
        // Counted, not asserted inside the closure: write() catches Throwable.
        config(['ai.providers.deepseek.key' => '', 'ai.providers.openrouter.key' => '']);
        $calls = 0;
        $writer = $this->writer(function () use (&$calls): array {
            $calls++;

            return ['fr' => 'Bonjour', 'en' => 'Hello'];
        });

        $this->assertNull($writer->write($this->scenario(), $this->target()));
        $this->assertSame(0, $calls, 'an unconfigured provider must not be dialled once per recipient');
    }

    public function test_a_thrown_generator_falls_back_to_the_template(): void
    {
        $writer = $this->writer(fn () => throw new \RuntimeException('provider down'));

        $this->assertNull($writer->write($this->scenario(), $this->target()));
    }

    /**
     * Returning null is what makes the caller use the template. Anything else
     * ships the model's mistake straight to a customer's WhatsApp.
     */
    public function test_degenerate_output_is_refused_rather_than_sent(): void
    {
        $cases = [
            'empty' => ['fr' => '', 'en' => 'Fine'],
            'missing locale' => ['fr' => 'Bonjour Awa'],
            'unresolved placeholder' => ['fr' => 'Bonjour :name, tu es à :progress%', 'en' => 'Hi there'],
            'handlebars leak' => ['fr' => 'Bonjour {{name}}', 'en' => 'Hi there'],
            'refusal' => ['fr' => "I'm sorry, I cannot write that.", 'en' => 'Hi there'],
            'preamble' => ['fr' => 'Here is your message: reprends ton cours', 'en' => 'Hi there'],
            'too long' => ['fr' => str_repeat('a', 321), 'en' => 'Hi there'],
        ];

        foreach ($cases as $label => $result) {
            $writer = $this->writer(fn () => $result);

            $this->assertNull($writer->write($this->scenario(), $this->target()), "{$label} should have been refused");
        }
    }

    public function test_a_failing_primary_falls_through_to_the_free_router(): void
    {
        $seen = [];
        $writer = $this->writer(function (string $prompt, string $provider) use (&$seen): array {
            $seen[] = $provider;

            if ($provider === 'deepseek') {
                throw new \RuntimeException('quota exhausted');
            }

            return ['fr' => 'Awa, reprends ton cours.', 'en' => 'Awa, pick your course back up.'];
        });

        $message = $writer->write($this->scenario(), $this->target());

        $this->assertSame(['deepseek', 'openrouter'], $seen);
        $this->assertStringContainsString('reprends ton cours', (string) $message);
    }

    public function test_unusable_output_from_the_primary_still_tries_the_router(): void
    {
        // A refusal from one provider must not cost the recipient the message
        // another would have written.
        $seen = [];
        $writer = $this->writer(function (string $prompt, string $provider) use (&$seen): array {
            $seen[] = $provider;

            return $provider === 'deepseek'
                ? ['fr' => "I'm sorry, I cannot write that.", 'en' => 'Hi there']
                : ['fr' => 'Awa, ton cours t\'attend.', 'en' => 'Awa, your course is waiting.'];
        });

        $this->assertNotNull($writer->write($this->scenario(), $this->target()));
        $this->assertSame(['deepseek', 'openrouter'], $seen);
    }

    public function test_an_unconfigured_provider_is_skipped_not_dialled(): void
    {
        config(['ai.providers.deepseek.key' => '']);
        $seen = [];
        $writer = $this->writer(function (string $prompt, string $provider) use (&$seen): array {
            $seen[] = $provider;

            return ['fr' => 'Awa, reprends.', 'en' => 'Awa, pick it up.'];
        });

        $this->assertNotNull($writer->write($this->scenario(), $this->target()));
        $this->assertSame(['openrouter'], $seen, 'a provider with no key must never be called');
    }

    public function test_the_prompt_carries_the_facts_the_template_had(): void
    {
        $prompt = (new RetentionCopywriter)->buildPrompt($this->scenario(), $this->target());

        $this->assertStringContainsString('learner_stalled', $prompt);
        $this->assertStringContainsString('Awa', $prompt);
        $this->assertStringContainsString('Media Buying', $prompt);
        $this->assertStringContainsString('40', $prompt);
        // Routing plumbing is not something to write about.
        $this->assertStringNotContainsString('environment_id', $prompt);
    }

    /**
     * Without the history in the prompt the second nudge is the first one
     * again — which is the whole complaint about the templates.
     */
    public function test_the_prompt_shows_what_was_already_sent_to_this_person(): void
    {
        RetentionMessage::create([
            'recipient_type' => 'learner',
            'recipient_id' => '4242',
            'scenario_key' => 'learner_stalled',
            'status' => RetentionMessage::STATUS_SENT,
            'meta' => ['channel' => 'whatsapp', 'body' => 'Awa, ton cours Media Buying t attend.'],
            'sent_at' => now()->subDays(8),
        ]);

        $prompt = (new RetentionCopywriter)->buildPrompt($this->scenario(), $this->target());

        $this->assertStringContainsString('Already sent to this person', $prompt);
        $this->assertStringContainsString('ton cours Media Buying t attend', $prompt);
    }

    public function test_a_first_contact_says_so_instead_of_showing_an_empty_history(): void
    {
        $prompt = (new RetentionCopywriter)->buildPrompt($this->scenario(), $this->target());

        $this->assertStringContainsString('first message this person has received', $prompt);
    }

    public function test_only_learner_stalled_is_personalised_so_far(): void
    {
        $personalised = collect(app(RetentionScenarioRegistry::class)->all())
            ->filter(fn (RetentionScenario $s) => $s->personalised)
            ->map(fn (RetentionScenario $s) => $s->key)
            ->values()
            ->all();

        $this->assertSame(['learner_stalled'], $personalised);
    }
}
