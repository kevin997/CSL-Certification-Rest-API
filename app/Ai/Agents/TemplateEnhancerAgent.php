<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Proposes ADDITIONS ONLY to an existing KURSA course template — new blocks
 * and new activities inside existing blocks — from an instructor's free-text
 * request plus the template's current structure. See
 * GenerateCourseDraftJob / CourseBuilderController for the enhance flow.
 *
 * This agent never modifies or deletes existing content: the frontend is
 * responsible for applying the returned additions via the existing
 * creation endpoints.
 *
 * NOTE: mirrors CourseBuilderAgent — see that class for why the fast 1b
 * model is PRIMARY here and the qwen model is the quality fallback, and why
 * the failover (schema + server-side normalization keep it safe) lives in
 * GenerateCourseDraftJob instead of being generalized into a shared concern.
 */
#[Provider(['ollama' => 'qwen2.5:14b', 'ollama_cpu' => 'llama3.2:1b'])]
#[Temperature(0.7)]
#[Timeout(480)]
class TemplateEnhancerAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are an expert instructional designer for KURSA, a multi-tenant course
platform. You will receive an existing course template's CURRENT structure
(its blocks, each with its id, and their existing activities) plus an
instructor's description of what they want to add.

Propose ONLY additions. Never repeat, rename, or otherwise reference removing
or changing anything that already exists — the current structure is
immutable. You may propose:
- New blocks (max 3), each with 1-5 new activities.
- New activities added to existing blocks (max 4 additional activities per
  existing block), referencing the block by its given id.

In `block_additions`, reference existing modules by their NUMBER exactly as
shown in the provided structure (`block_number`: 1 for "Module 1", 2 for
"Module 2", and so on).

Unless the request is impossible, you MUST propose at least one addition.
Only propose additions that fulfil the instructor's request and complement
what already exists in the template — do not repeat existing activities.

Each activity's `type` MUST be one of: text, video, quiz, lesson, assignment,
documentation, feedback, certificate.

Write everything in the requested language. Titles should be short and
actionable. Keep ALL descriptions under 20 words — this is a skeleton the
teacher will flesh out, not course prose.

If there is nothing sensible to add for the request, return empty arrays for
both `new_blocks` and `block_additions` rather than inventing filler content.
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
            'new_blocks' => $schema->array()->required()->items(
                $schema->object([
                    'title' => $schema->string()->required(),
                    'description' => $schema->string()->required(),
                    'activities' => $schema->array()->required()->items(
                        $schema->object([
                            'title' => $schema->string()->required(),
                            'type' => $schema->string()->required(),
                            'description' => $schema->string()->required(),
                        ])
                    ),
                ])
            ),
            'block_additions' => $schema->array()->required()->items(
                $schema->object([
                    'block_number' => $schema->integer()->required(),
                    'activities' => $schema->array()->required()->items(
                        $schema->object([
                            'title' => $schema->string()->required(),
                            'type' => $schema->string()->required(),
                            'description' => $schema->string()->required(),
                        ])
                    ),
                ])
            ),
        ];
    }
}
