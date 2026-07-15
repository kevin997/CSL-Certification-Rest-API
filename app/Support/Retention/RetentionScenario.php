<?php

namespace App\Support\Retention;

use App\Support\Marketing\Multilingual;
use Illuminate\Support\Collection;

/**
 * A retention scenario: a behavioural trigger + the message it sends.
 *
 * - `key`          stable id, also used for per-scenario cooldowns + audit.
 * - `audience`     instructor | learner.
 * - `priority`     higher wins when a recipient matches several scenarios in one
 *                  run (each recipient gets at most one message per run).
 * - `cooldownDays` don't re-send THIS scenario to the same recipient within N days.
 * - `resolver`     callable returning Collection<RetentionTarget> of who matches now.
 * - `messageKey`   translation key; rendered per-target with its context + name.
 *
 * Ported from shopikat's RetentionScenario, minus the coupon-issuing
 * machinery (KURSA has no discount-code system to mint from).
 */
class RetentionScenario
{
    public const INSTRUCTOR = 'instructor';

    public const LEARNER = 'learner';

    /**
     * @param  callable(): Collection<int, RetentionTarget>  $resolver
     */
    public function __construct(
        public string $key,
        public string $audience,
        public int $priority,
        public int $cooldownDays,
        public mixed $resolver,
        public string $messageKey,
    ) {}

    /**
     * @return Collection<int, RetentionTarget>
     */
    public function targets(): Collection
    {
        return ($this->resolver)();
    }

    /**
     * Render the message for a target in the given locale. First name only, so
     * it reads like a personal note rather than a form letter.
     *
     * Renders in every configured language (e.g. FR + EN), joined.
     */
    public function render(RetentionTarget $target): string
    {
        $firstName = trim((string) (preg_split('/\s+/', trim($target->name))[0] ?? ''));

        return Multilingual::render(fn (string $locale): string => trans($this->messageKey, array_merge($target->context, [
            'name' => $firstName !== '' ? $firstName : trans('retention.fallback_name', [], $locale),
        ]), $locale));
    }
}
