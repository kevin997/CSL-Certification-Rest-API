<?php

namespace App\Ai\Agents;

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
 * Generates a complete KURSA course template (template -> blocks -> activities)
 * from an instructor's free-text description — see GenerateCourseDraftJob /
 * CourseBuilderController.
 *
 * NOTE: This agent deliberately does NOT use
 * App\Ai\Agents\Concerns\FailsOverToFallbackModel — that concern hardcodes its
 * fallback to `llama3.2:1b`, but the course builder needs a larger fallback
 * — the shared CPU box (often at load 6-8 from transcoding) cannot run 7B
 * models inside the job budget, so the fast 1b model is PRIMARY here; qwen is
 * the quality fallback. Flip the Model attribute when a GPU box arrives. The
 * failover (schema + server-side normalization keep it safe) lives in
 * GenerateCourseDraftJob instead of being generalized into the shared concern.
 */
#[Provider(Lab::Ollama)]
#[Model('llama3.2:1b')]
#[Temperature(0.7)]
#[Timeout(480)]
class CourseBuilderAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are an expert instructional designer for KURSA, a multi-tenant course
platform. Given an instructor's description of a course, design a complete,
pedagogically sound course template, written in the requested language.

Structure the course as 3-5 blocks (modules), in progressive order, with 2-4
activities per block. Keep ALL descriptions under 20 words — this is a
skeleton the teacher will flesh out, not course prose. Each activity's `type` MUST be one of: text, video,
quiz, lesson, assignment, documentation, feedback, certificate. Prefer mostly
lesson/video/text activities for teaching content, a quiz to check
understanding, and an assignment for practice. If appropriate, add exactly
ONE certificate activity at the very end of the last block.

Titles should be short and actionable. Descriptions should be 1-2 sentences
telling the teacher what to put there.
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
            'title' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            'blocks' => $schema->array()->required()->items(
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
        ];
    }
}
