<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\FailsOverToFallbackModel;
use App\Ai\Agents\Concerns\HasMarketingCopywriterInstructions;
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
 * Generates one punchy WhatsApp-Status line per language about a KURSA
 * feature or blog article — see GenerateMarketingContentCommand.
 */
#[Provider(Lab::Ollama)]
#[Model('qwen2.5:7b')]
#[Temperature(0.7)]
#[Timeout(300)]
class StatusTeaserAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use FailsOverToFallbackModel;
    use HasMarketingCopywriterInstructions;
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return self::MARKETING_SYSTEM_PROMPT."\n\n".
            'You will be given either a KURSA feature (name + what it does) or a '.
            'blog article excerpt (title + excerpt). Write ONE punchy, '.
            'curiosity-driving WhatsApp-Status line per language (max ~140 '.
            'characters each) about it.';
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
            'topic' => $schema->string()->required(),
            'fr' => $schema->string()->required(),
            'en' => $schema->string()->required(),
        ];
    }
}
