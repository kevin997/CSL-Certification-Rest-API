<?php

namespace App\Services\Marketing;

use App\Ai\Agents\AutomationMessageAgent;
use App\Models\Environment;
use App\Models\MarketingAutomation;
use RuntimeException;

/**
 * Produces an editable draft of the email + WhatsApp copy for one marketing
 * automation trigger. Nothing is persisted here; the instructor reviews the
 * draft in /settings/integrations/automations and saves it explicitly.
 */
class AutomationCopyGenerator
{
    private const MAX_SUBJECT = 255;

    private const MAX_BODY = 10000;

    private const MAX_WHATSAPP = 4000;

    /**
     * @param  string[]  $channels
     * @return array{email_subject: string, email_body: string, whatsapp_template: string}
     */
    public function generate(
        Environment $environment,
        string $trigger,
        array $channels,
        ?string $instructions = null,
        string $language = 'fr',
    ): array {
        $response = (new AutomationMessageAgent)->promptWithFailover(
            $this->prompt($environment, $trigger, $channels, $instructions, $language)
        );

        return $this->normalize($response->toArray());
    }

    /**
     * @param  array<string, mixed>  $copy
     * @return array{email_subject: string, email_body: string, whatsapp_template: string}
     */
    public function normalize(array $copy): array
    {
        $subject = $this->clean((string) ($copy['email_subject'] ?? ''));
        $body = $this->clean((string) ($copy['email_body'] ?? ''));
        $whatsapp = $this->clean((string) ($copy['whatsapp_template'] ?? ''));

        if ($subject === '' || $body === '' || $whatsapp === '') {
            throw new RuntimeException('The model returned an incomplete message.');
        }

        $body = $this->withContinueUrl($body, "\n\n");
        $whatsapp = $this->withContinueUrl($whatsapp, ' ');

        return [
            'email_subject' => mb_substr($subject, 0, self::MAX_SUBJECT),
            'email_body' => mb_substr($body, 0, self::MAX_BODY),
            'whatsapp_template' => mb_substr($whatsapp, 0, self::MAX_WHATSAPP),
        ];
    }

    /**
     * @param  string[]  $channels
     */
    private function prompt(
        Environment $environment,
        string $trigger,
        array $channels,
        ?string $instructions,
        string $language,
    ): string {
        $facts = match ($trigger) {
            MarketingAutomation::TRIGGER_ORDER_PLACED => 'The customer has just placed order {{order_number}} for {{total}} {{currency}} at {{store_name}}. Payment is still pending: invite them to finish paying with {{continue_url}}.',
            MarketingAutomation::TRIGGER_PAYMENT_CONFIRMED => 'Payment for order {{order_number}} ({{total}} {{currency}}) at {{store_name}} is confirmed. Thank the customer and keep {{continue_url}} for their records.',
            MarketingAutomation::TRIGGER_ORDER_ABANDONED => 'Order {{order_number}} for {{total}} {{currency}} at {{store_name}} is still unpaid. Send a gentle reminder and the continue-payment link {{continue_url}}.',
            MarketingAutomation::TRIGGER_FORM_SUBMITTED => 'A lead just submitted a marketing form. Summarise the lead and keep {{continue_url}} available as the checkout link.',
            default => 'Use the placeholders exactly as provided.',
        };

        $recipient = MarketingAutomation::DEFAULT_RECIPIENTS[$trigger] ?? MarketingAutomation::RECIPIENT_CUSTOMER;
        $languageName = $language === 'en' ? 'English' : 'French';
        $channelList = implode(' and ', array_map(
            fn (string $channel) => $channel === 'whatsapp' ? 'WhatsApp' : 'email',
            $channels ?: [MarketingAutomation::CHANNEL_EMAIL, MarketingAutomation::CHANNEL_WHATSAPP]
        ));

        return implode("\n\n", array_filter([
            "Store: {$environment->name}",
            "Trigger: {$trigger}",
            "Recipient: {$recipient}",
            "Channels: {$channelList}",
            "Language: {$languageName}",
            "Facts: {$facts}",
            $instructions ? "Operator instructions: {$instructions}" : null,
            'Return the email subject, email body and WhatsApp message. Keep every placeholder literal, including {{continue_url}}.',
        ]));
    }

    private function clean(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $value) ?? $value;
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

        return trim($value);
    }

    private function withContinueUrl(string $text, string $separator): string
    {
        if (str_contains($text, '{{continue_url}}')) {
            return $text;
        }

        return rtrim($text).$separator.'{{continue_url}}';
    }
}
