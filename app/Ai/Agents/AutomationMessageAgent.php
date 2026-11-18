<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\FailsOverToFallbackModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Writes the email + WhatsApp copy for one marketing automation trigger.
 *
 * The output is a draft: the instructor edits it in
 * /settings/integrations/automations and saves it explicitly. Placeholders are
 * literal text substituted at send time by MarketingAutomationService, so the
 * model must return them untouched.
 */
#[Provider(['deepseek' => 'deepseek-chat', 'openrouter' => 'openrouter/free'])]
#[Temperature(0.7)]
#[Timeout(60)]
class AutomationMessageAgent implements Agent, HasStructuredOutput
{
    use FailsOverToFallbackModel;
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You write transactional email and WhatsApp messages for KURSA, an online
        course platform used mainly in francophone Africa.

        Rules, in order of importance:
        1. Write in the requested language. Default to French.
        2. Use only these placeholders, copied exactly, braces included:
           {{name}}, {{email}}, {{phone}}, {{form_title}}, {{order_number}},
           {{product_name}}, {{total}}, {{currency}}, {{store_name}},
           {{continue_url}}. Never invent an order number, amount, discount,
           deadline or link.
        3. Both the email body and the WhatsApp message must contain
           {{continue_url}} exactly once. It is the continue-payment link.
           For order triggers, name the product the customer is paying for with
           {{product_name}}.
        4. Email subject: at most 90 characters, no emoji. Email body: 2 to 5
           short plain-text paragraphs separated by blank lines, no HTML and no
           markdown. WhatsApp: 1 to 3 short sentences that read like a person
           typing, at most one emoji, no subject line.
        5. Be warm, direct and specific. Do not manufacture urgency.
        6. If operator instructions are given, follow them while keeping every
           placeholder and the continue-payment link.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'email_subject' => $schema->string()
                ->description('Plain-text email subject, max 90 characters.')
                ->required(),
            'email_body' => $schema->string()
                ->description('Plain-text email body, paragraphs separated by blank lines, includes {{continue_url}}.')
                ->required(),
            'whatsapp_template' => $schema->string()
                ->description('Short conversational WhatsApp message, includes {{continue_url}}.')
                ->required(),
        ];
    }
}
