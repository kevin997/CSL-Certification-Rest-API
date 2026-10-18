<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotification;
use App\Mail\AutomationMail;
use App\Models\MarketingConsent;
use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Support\Sales\SalesFormCheckoutToken;
use Illuminate\Support\Facades\Mail;

class SalesFormPendingReminderService
{
    public function send(Order $order): bool
    {
        $order->loadMissing(['salesFormSubmission.salesForm.fields', 'environment']);
        $submission = $order->salesFormSubmission;

        if ($order->status !== Order::STATUS_PENDING || ! $submission || $submission->status !== SalesFormSubmission::STATUS_PENDING) {
            return false;
        }

        $language = $this->languageFor($submission);
        $name = trim((string) ($submission->name ?: $order->billing_name));
        $form = (string) ($submission->salesForm?->title ?? 'KURSA');
        $url = (string) ($order->continue_payment_url ?? '');
        if ($url !== '') {
            $query = [
                'checkout_token' => SalesFormCheckoutToken::issue($order),
                'registration' => $submission->id,
            ];
            $gateway = $submission->salesForm?->settings['payment_gateway_code'] ?? null;
            if (is_string($gateway) && $gateway !== '') {
                $query['payment_gateway'] = $gateway;
            }
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        if ($language === 'fr') {
            $subject = 'Votre inscription à '.$form.' est en attente';
            $message = "Bonjour {$name}, votre inscription à {$form} est en attente de paiement (commande {$order->order_number}). Terminez votre paiement ici : {$url}. Si vous avez déjà payé, contactez-nous avec votre numéro de commande.";
        } else {
            $subject = 'Your registration for '.$form.' is awaiting payment';
            $message = "Hello {$name}, your registration for {$form} is awaiting payment (order {$order->order_number}). Complete your payment here: {$url}. If you have already paid, contact us with your order number.";
        }

        $sent = false;
        if (filled($submission->email) && MarketingConsent::isGranted($submission, 'email')) {
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
