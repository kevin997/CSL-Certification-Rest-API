<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotification;
use App\Mail\AutomationMail;
use App\Models\Environment;
use App\Models\MarketingAutomation;
use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Services\Sales\OrderContinueUrl;
use App\Services\Sales\ShortLinkService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Executes per-environment marketing automations: resolves the recipient,
 * substitutes {{placeholders}} into the configured templates, and dispatches
 * the Email/WhatsApp sends. Modeled on shopikat's messaging architecture.
 */
class MarketingAutomationService
{
    public function __construct(
        private readonly ShortLinkService $shortLinks,
        private readonly OrderContinueUrl $continueUrls,
    ) {}

    /**
     * Run the form_submitted automation for a sales form submission.
     */
    public function handleFormSubmitted(SalesFormSubmission $submission): void
    {
        $automation = MarketingAutomation::enabledFor($submission->environment_id, MarketingAutomation::TRIGGER_FORM_SUBMITTED);
        if (! $automation) {
            return;
        }

        $submission->loadMissing(['salesForm', 'salesForm.environment']);
        $environment = $submission->salesForm?->environment
            ?? Environment::find($submission->environment_id);

        $variables = [
            '{{name}}' => (string) ($submission->name ?? ''),
            '{{email}}' => (string) ($submission->email ?? ''),
            '{{phone}}' => (string) ($submission->phone ?? ''),
            '{{form_title}}' => (string) ($submission->salesForm?->title ?? ''),
            '{{order_number}}' => '',
            '{{total}}' => '',
            '{{currency}}' => '',
            '{{store_name}}' => (string) ($environment?->name ?? ''),
            '{{continue_url}}' => '',
        ];

        $this->dispatchSends($automation, $environment, $variables, [
            'email' => $submission->email,
            'phone' => $submission->phone,
            'name' => $submission->name,
        ]);
    }

    /**
     * Run an order-based automation (order_placed | payment_confirmed | order_abandoned).
     */
    public function handleOrderTrigger(Order $order, string $trigger): void
    {
        if ($trigger === MarketingAutomation::TRIGGER_ORDER_ABANDONED && $order->status !== Order::STATUS_PENDING) {
            return;
        }

        $automation = MarketingAutomation::enabledFor($order->environment_id, $trigger);
        if (! $automation) {
            return;
        }

        $order->loadMissing(['environment', 'user', 'salesFormSubmission']);

        $variables = [
            '{{name}}' => (string) ($order->billing_name ?? $order->user?->name ?? ''),
            '{{email}}' => (string) ($order->billing_email ?? $order->user?->email ?? ''),
            '{{phone}}' => (string) ($order->phone_number ?: ($order->user?->whatsapp_number ?: ($order->salesFormSubmission?->phone ?? ''))),
            '{{form_title}}' => '',
            '{{order_number}}' => (string) ($order->order_number ?? ''),
            '{{total}}' => number_format((float) $order->total_amount, 2),
            '{{currency}}' => (string) ($order->currency ?? ''),
            '{{store_name}}' => (string) ($order->environment?->name ?? ''),
            '{{continue_url}}' => $this->shortLinks->forOrder($order) ?: $this->continueUrls->for($order),
        ];

        $this->dispatchSends($automation, $order->environment, $variables, [
            'email' => $order->billing_email ?? $order->user?->email,
            'phone' => $order->phone_number ?: ($order->user?->whatsapp_number ?: $order->salesFormSubmission?->phone),
            'name' => $order->billing_name ?? $order->user?->name,
        ], in_array($trigger, [
            MarketingAutomation::TRIGGER_ORDER_PLACED,
            MarketingAutomation::TRIGGER_ORDER_ABANDONED,
        ], true) ? $order->id : null);
    }

    /**
     * Resolve the recipient and dispatch the configured channel sends.
     *
     * @param  array{email: ?string, phone: ?string, name: ?string}  $customer
     */
    private function dispatchSends(
        MarketingAutomation $automation,
        ?Environment $environment,
        array $variables,
        array $customer,
        ?int $pendingOrderId = null,
    ): void {
        [$toEmail, $toPhone] = $this->resolveRecipient($automation, $environment, $customer);

        if ($automation->usesChannel(MarketingAutomation::CHANNEL_EMAIL) && filled($toEmail)) {
            $subject = $this->substitute((string) $automation->email_subject, $variables);
            $body = $this->substitute((string) $automation->email_body, $variables);

            if (filled($subject) && filled($body)) {
                Mail::to($toEmail)->send(new AutomationMail($subject, $body, $environment));
            }
        }

        if ($automation->usesChannel(MarketingAutomation::CHANNEL_WHATSAPP) && filled($toPhone)) {
            $message = $this->substitute((string) $automation->whatsapp_template, $variables);

            if (filled($message)) {
                SendWhatsAppNotification::dispatch($this->normalizePhone($toPhone), $message, $pendingOrderId)->afterCommit();
            }
        }

        Log::info('Marketing automation dispatched', [
            'trigger' => $automation->trigger,
            'environment_id' => $automation->environment_id,
            'recipient' => $automation->recipient,
            'email' => filled($toEmail),
            'whatsapp' => filled($toPhone),
        ]);
    }

    /**
     * @param  array{email: ?string, phone: ?string, name: ?string}  $customer
     * @return array{0: ?string, 1: ?string} [email, phone]
     */
    private function resolveRecipient(MarketingAutomation $automation, ?Environment $environment, array $customer): array
    {
        if ($automation->recipient === MarketingAutomation::RECIPIENT_INSTRUCTOR) {
            $owner = $environment?->owner;

            return [$owner?->email, $owner?->whatsapp_number];
        }

        return [$customer['email'] ?? null, $customer['phone'] ?? null];
    }

    /**
     * Plain string substitution — templates are user-supplied, never Blade-evaluated.
     */
    private function substitute(string $template, array $variables): string
    {
        return str_replace(array_keys($variables), array_values($variables), $template);
    }

    /**
     * Strip everything but digits (wa-friendly), keeping a leading +.
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone) ?? $phone;
    }
}
