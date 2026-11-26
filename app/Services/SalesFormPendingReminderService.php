<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotification;
use App\Mail\AutomationMail;
use App\Models\MarketingConsent;
use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Services\Sales\ShortLinkService;
use Illuminate\Support\Facades\Mail;

class SalesFormPendingReminderService
{
    public function __construct(
        private readonly ShortLinkService $shortLinks,
    ) {}

    public function send(Order $order): bool
    {
        $order->loadMissing(['salesFormSubmission.salesForm.fields', 'environment', 'items.product']);
        $submission = $order->salesFormSubmission;

        if ($order->status !== Order::STATUS_PENDING || ! $submission || $submission->status !== SalesFormSubmission::STATUS_PENDING) {
            return false;
        }

        $language = $this->languageFor($submission);
        $name = trim((string) ($submission->name ?: $order->billing_name));
        $form = (string) ($submission->salesForm?->title ?? 'KURSA');
        $productName = $order->items
            ->map(fn ($item) => $item->product?->name)
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');
        $productLabel = $productName !== '' ? $productName : $form;
        $url = $this->shortLinks->forOrder($order);

        if ($language === 'fr') {
            $subject = 'Votre paiement pour '.$productLabel.' est en attente';
            $message = "Bonjour {$name}, votre commande {$order->order_number} pour {$productLabel} est en attente de paiement. Terminez votre paiement ici : {$url}. Si vous avez déjà payé, contactez-nous avec votre numéro de commande.";
        } else {
            $subject = 'Your payment for '.$productLabel.' is awaiting payment';
            $message = "Hello {$name}, your order {$order->order_number} for {$productLabel} is awaiting payment. Complete your payment here: {$url}. If you have already paid, contact us with your order number.";
        }

        $sent = false;
        if (config('marketing.email_enabled') && filled($submission->email) && MarketingConsent::isGranted($submission, 'email')) {
            Mail::to($submission->email)->send(new AutomationMail($subject, $message, $order->environment));
            $sent = true;
        }

        if (filled($submission->phone) && MarketingConsent::isGranted($submission, 'whatsapp') && WachapNotificationService::isConfigured()) {
            $phone = preg_replace('/[^0-9]/', '', $submission->phone);
            if (filled($phone)) {
                SendWhatsAppNotification::dispatch($phone, $message, $order->id)->afterCommit();
                $sent = true;
            }
        }

        return $sent;
    }

    private function languageFor(SalesFormSubmission $submission): string
    {
        $answers = collect($submission->answers ?? [])
            ->mapWithKeys(fn ($value, $key) => [strtolower((string) $key) => is_scalar($value) ? strtolower(trim((string) $value)) : '']);

        $explicit = $answers->first(fn ($value, $key) => in_array($key, ['language', 'langue', 'locale'], true));
        if ($explicit) {
            return str_starts_with($explicit, 'fr') || str_contains($explicit, 'fran') ? 'fr' : 'en';
        }

        $countryKey = $submission->salesForm?->fields->firstWhere('type', 'country')?->field_key;
        $regionKey = $submission->salesForm?->fields->firstWhere('type', 'state')?->field_key;
        $country = $answers->get(strtolower((string) $countryKey))
            ?: ($answers->first(fn ($value, $key) => in_array($key, ['country', 'pays', 'country_code'], true)) ?? '');
        $region = $answers->get(strtolower((string) $regionKey))
            ?: ($answers->first(fn ($value, $key) => in_array($key, ['state', 'region', 'state_region', 'province'], true)) ?? '');

        if ($country === '') {
            $phone = preg_replace('/[^0-9]/', '', (string) $submission->phone);
            if (str_starts_with($phone, '242')) {
                $country = 'congo';
            } elseif (str_starts_with($phone, '237')) {
                $country = 'cameroon';
            }
        }

        if (in_array($country, ['cameroon', 'cameroun', 'cm'], true)) {
            return in_array($region, ['northwest', 'north west', 'southwest', 'south west', 'nord-ouest', 'sud-ouest'], true) ? 'en' : 'fr';
        }

        return in_array($country, [
            'congo', 'republic of the congo', 'république du congo', 'cg',
            'dr congo', 'drc', 'rdc', 'cd', 'democratic republic of the congo',
            'gabon', 'ga', 'senegal', 'sénégal', 'sn', 'ivory coast', "côte d'ivoire", 'ci',
            'benin', 'bénin', 'bj', 'togo', 'tg', 'chad', 'tchad', 'td',
        ], true) ? 'fr' : 'en';
    }
}
