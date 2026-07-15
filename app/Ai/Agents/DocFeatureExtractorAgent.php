<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\FailsOverToFallbackModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Extracts concrete, sellable KURSA features from a documentation excerpt —
 * see FeatureInventoryService.
 */
#[Provider(Lab::Ollama)]
#[Model('qwen2.5:7b')]
#[Temperature(0.3)]
#[Timeout(300)]
class DocFeatureExtractorAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use FailsOverToFallbackModel;
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are analyzing internal documentation for KURSA, a multi-tenant course and
certification platform. Instructors build branded academies on KURSA: they
create courses, quizzes, certificates, storefronts, and sales funnels, accept
payments (including African mobile money), run live sessions, and chat with
learners. Learners enroll, learn, and earn certificates.

Read the documentation excerpt and extract between 0 and 5 concrete, sellable
features that instructors or learners would care about. Ignore internal
implementation details, infrastructure, and code-only concerns. If nothing
sellable is present, return an empty features list.
PROMPT;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'features' => $schema->array()->required()->items(
                $schema->object([
                    'name' => $schema->string()->required(),
                    'summary' => $schema->string()->required(),
                    'audience' => $schema->string()->enum(['instructor', 'learner', 'both'])->required(),
                ])
            ),
        ];
    }
}
