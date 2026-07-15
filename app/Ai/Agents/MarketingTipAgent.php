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
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Generates a practical "group tip" (2-4 sentences per language) about a
 * KURSA feature or blog article — see GenerateMarketingContentCommand.
 */
#[Provider(['ollama' => 'qwen2.5:14b', 'ollama_cpu' => 'llama3.2:1b'])]
#[Temperature(0.8)]
#[Timeout(300)]
class MarketingTipAgent implements Agent, Conversational, HasStructuredOutput, HasTools
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
            'blog article excerpt (title + excerpt). Write a practical tip / '.
            'mini-guide (2-4 sentences per language) teaching the reader how and '.
            'why to use the feature, or what actionable takeaway to apply from '.
            'the article, ending with one clear actionable step (or an '.
            'invitation to read more, for articles).';
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
