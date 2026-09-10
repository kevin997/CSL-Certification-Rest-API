<?php

namespace App\Support\Retention;

use App\Ai\Agents\RetentionCopywriterAgent;
use App\Models\RetentionMessage;
use App\Support\Marketing\Multilingual;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes a retention nudge for one person, or gives up and lets the caller
 * fall back to the scenario template.
 *
 * The templates this replaces are the reason retention reads as boilerplate:
 * every learner matching a scenario receives the identical sentence with their
 * first name substituted, again at every cooldown. This asks a model for the
 * same message using the same facts, plus the ones already sent to this
 * person, so the second nudge is not the first one again.
 *
 * Every failure path returns null. A generic message beats no message, so the
 * template stays the floor and this is only ever an improvement on top.
 */
class RetentionCopywriter
{
    /** How many previous messages to show the model so it can avoid repeating them. */
    private const HISTORY_DEPTH = 3;

    /** The locales this writer can produce; anything else falls back to the template. */
    private const SUPPORTED_LOCALES = ['fr', 'en'];

    /**
     * Providers to try, best first. DeepSeek writes the better message;
     * openrouter/free is OpenRouter's Free Models Router — zero cost, a random
     * free model each time, so it is a degradation and not an equal. The
     * scenario template remains the floor under both.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const ATTEMPTS = [
        ['deepseek', 'deepseek-chat'],
        ['openrouter', 'openrouter/free'],
    ];

    /** @var (\Closure(string, string, string): array<string, mixed>)|null */
    private $generator;

    /**
     * The generator is injected so the seam can be tested without a model
     * behind it — what matters here is the fallback and the rejection rules,
     * not the provider.
     *
     * @param  (\Closure(string, string, string): array<string, mixed>)|null  $generator
     */
    public function __construct(?\Closure $generator = null)
    {
        $this->generator = $generator;
    }

    /**
     * Whether the provider behind the copywriter has credentials. Without this
     * an unconfigured deployment would make one doomed HTTP call per recipient
     * on every run before falling back — the templates would still go out, but
     * a thousand-recipient run would spend a thousand round trips learning the
     * same thing.
     */
    public static function isConfigured(): bool
    {
        return self::configuredAttempts() !== [];
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private static function configuredAttempts(): array
    {
        return array_values(array_filter(
            self::ATTEMPTS,
            fn (array $attempt): bool => filled(config("ai.providers.{$attempt[0]}.key")),
        ));
    }

    public function write(RetentionScenario $scenario, RetentionTarget $target): ?string
    {
        if (! $scenario->personalised || ! self::isConfigured()) {
            return null;
        }

        $locales = Multilingual::locales();

        if (array_diff($locales, self::SUPPORTED_LOCALES) !== []) {
            // A locale we cannot ask for; the template covers every configured
            // language, so it is the safer answer here.
            return null;
        }

        $generate = $this->generator ?? static fn (string $prompt, string $provider, string $model): array
            => (array) (new RetentionCopywriterAgent)->prompt($prompt, provider: $provider, model: $model);

        $prompt = $this->buildPrompt($scenario, $target);

        foreach (self::configuredAttempts() as [$provider, $model]) {
            try {
                $result = $generate($prompt, $provider, $model);
            } catch (\Throwable $e) {
                Log::warning('RetentionCopywriter: provider failed, trying the next one', [
                    'scenario' => $scenario->key,
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $message = $this->assemble($result, $locales, $target);

            if ($message !== null) {
                return $message;
            }

            // Output this provider cannot be trusted with. Try the next rather
            // than give up: a free model producing nonsense should not cost the
            // recipient the personalised message a paid one would have written.
            Log::warning('RetentionCopywriter: provider returned unusable output', [
                'scenario' => $scenario->key,
                'provider' => $provider,
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<int, string>  $locales
     */
    private function assemble(array $result, array $locales, RetentionTarget $target): ?string
    {
        $parts = [];

        foreach ($locales as $locale) {
            $text = trim((string) ($result[$locale] ?? ''));

            if ($this->isUnusable($text, $target)) {
                return null;
            }

            $parts[] = $text;
        }

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    /**
     * The facts the template had, plus what this person was already told.
     */
    public function buildPrompt(RetentionScenario $scenario, RetentionTarget $target): string
    {
        $firstName = trim((string) (preg_split('/\s+/', trim($target->name))[0] ?? ''));

        $facts = ["Scenario: {$scenario->key}", 'First name: '.($firstName !== '' ? $firstName : 'unknown')];

        foreach ($target->context as $key => $value) {
            // Routing hints are plumbing, not something to write about.
            if (in_array($key, ['environment_id', 'environment_url'], true) || $value === null || $value === '') {
                continue;
            }

            $facts[] = Str::headline((string) $key).': '.$value;
        }

        $previous = $this->previousMessages($target);

        if ($previous !== []) {
            $facts[] = "\nAlready sent to this person (do not repeat these, and do not reuse their opening):";
            foreach ($previous as $index => $body) {
                $facts[] = '  '.($index + 1).'. '.Str::of($body)->squish()->limit(200);
            }
        } else {
            $facts[] = "\nThis is the first message this person has received.";
        }

        return implode("\n", $facts);
    }

    /**
     * @return array<int, string>
     */
    private function previousMessages(RetentionTarget $target): array
    {
        return RetentionMessage::query()
            ->where('recipient_type', $target->recipientType)
            ->where('recipient_id', $target->recipientId)
            ->whereNotNull('meta')
            ->latest('created_at')
            ->limit(self::HISTORY_DEPTH)
            ->pluck('meta')
            ->map(fn ($meta) => is_array($meta) ? (string) ($meta['body'] ?? '') : '')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Nothing reviews this before it reaches a customer's phone, so anything
     * degenerate has to die here rather than be sent.
     */
    private function isUnusable(string $text, RetentionTarget $target): bool
    {
        if ($text === '' || mb_strlen($text) > 320) {
            return true;
        }

        // A model that echoes an unresolved placeholder has not understood the
        // facts it was given.
        if (str_contains($text, ':name') || preg_match('/\{\{|\}\}|:progress|:course/', $text) === 1) {
            return true;
        }

        // Refusals and meta-commentary read as a bug to the person receiving them.
        return (bool) preg_match('/^(as an ai|i cannot|i\'m sorry|here is|here\'s|sure[,!])/i', $text);
    }
}
