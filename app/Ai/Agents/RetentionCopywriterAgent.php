<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Writes one retention nudge for one person.
 *
 * The scenario templates it replaces say the same sentence to everyone who
 * matches — "Vous avez progressé à :progress% sur « :course »" — every cooldown
 * period, forever. This agent gets the same facts the template had, plus what
 * was already said to this person, and is asked not to repeat it.
 *
 * Deliberately NOT routed through the OpenClaw gateway. OpenClaw carries its
 * agent context on every call — measured at 34k-53k prompt tokens to answer
 * "OK" — which is worth paying for a conversation and absurd for a one-shot
 * nudge sent to thousands of people. It routes to deepseek-v3 anyway, so this
 * calls the same model with a ~600 token prompt. Switching back is one
 * attribute: #[Provider(['openclaw' => 'openclaw/main'])].
 */
#[Provider(['deepseek' => 'deepseek-chat'])]
#[Temperature(0.9)]
#[Timeout(45)]
class RetentionCopywriterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You write short retention messages for KURSA, an online course platform
        used mainly in francophone Africa. The message is delivered over
        WhatsApp, so it must read like a person typing, not a newsletter.

        Rules, in order of importance:
        1. Never repeat a message the learner has already received. You are
           given the recent ones — say something different, with a different
           angle and a different opening.
        2. Use the facts you are given, and only those. Never invent a course
           name, a number, a deadline, a discount or a feature.
        3. One or two sentences. No greeting block, no signature, no subject
           line, no link — a link is appended afterwards.
        4. Address the learner by first name, informally, in the language asked
           for. At most one emoji, and only if it earns its place.
        5. If nothing new can honestly be said, write the plainest useful
           reminder rather than manufacturing urgency.
        PROMPT;
    }

    /**
     * Both locales in one call — the same shape MarketingTipAgent returns, and
     * one round trip instead of two per recipient.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'fr' => $schema->string()->description('The message in French. One or two sentences.')->required(),
            'en' => $schema->string()->description('The same message in English. Not a literal translation — it should read naturally.')->required(),
        ];
    }
}
