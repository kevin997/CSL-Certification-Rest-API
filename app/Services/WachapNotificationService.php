<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp sending via the Wachap API v1 (ported from the shopikat project).
 *
 * @see https://api-doc.wachap.com
 */
class WachapNotificationService
{
    /**
     * Send a text message via WhatsApp.
     */
    public function sendWhatsApp(string $phoneNumber, string $message): Response
    {
        return $this->send([
            'to' => $phoneNumber,
            'type' => 'text',
            'content' => $message,
        ]);
    }

    /**
     * Send an image message via WhatsApp.
     *
     * @param  array{url: string, caption?: string}  $imageData
     */
    public function sendImage(string $phoneNumber, array $imageData): Response
    {
        return $this->send([
            'to' => $phoneNumber,
            'type' => 'image',
            'content' => $imageData,
        ]);
    }

    /**
     * Send a document via WhatsApp.
     *
     * @param  array{url: string, filename?: string, caption?: string}  $documentData
     */
    public function sendDocument(string $phoneNumber, array $documentData): Response
    {
        return $this->send([
            'to' => $phoneNumber,
            'type' => 'document',
            'content' => $documentData,
        ]);
    }

    /**
     * Post a WhatsApp Status (story) — used for daily marketing broadcasts.
     * POST /v1/whatsapp/status/post (flat body, NOT wrapped in `data`).
     *
     * @param  'text'|'image'|'video'  $type
     * @param  array{imageUrl?:string, videoUrl?:string, backgroundColor?:string, font?:int, privacyType?:string}  $options
     */
    public function postStatus(string $type, string $content, array $options = []): Response
    {
        return $this->post('/v1/whatsapp/status/post', array_merge([
            'accountId' => config('services.wachap.account_id'),
            'type' => $type,
            'content' => $content,
        ], $options));
    }

    /**
     * Post a message to a WhatsApp Group (jid ends in @g.us).
     * POST /v1/whatsapp/groups/send.
     */
    public function sendGroupMessage(string $groupJid, string $content, string $type = 'text'): Response
    {
        return $this->post('/v1/whatsapp/groups/send', [
            'accountId' => config('services.wachap.account_id'),
            'groupJid' => $groupJid,
            'type' => $type,
            'content' => $content,
        ]);
    }

    /**
     * Post a message to a WhatsApp Channel (newsletter).
     * POST /v1/whatsapp/newsletters/send.
     */
    public function sendChannelMessage(string $newsletterJid, string $content, string $type = 'text'): Response
    {
        return $this->post('/v1/whatsapp/newsletters/send', [
            'accountId' => config('services.wachap.account_id'),
            'newsletterJid' => $newsletterJid,
            'type' => $type,
            'content' => $content,
        ]);
    }

    /**
     * Ask Wachap which of the given numbers are actually on WhatsApp.
     * POST /v1/whatsapp/contacts/check. FAIL-OPEN: on any error or unrecognised
     * response shape, returns all input numbers (validation is an optimisation,
     * never a hard gate on sending).
     *
     * @param  array<int, string>  $phones  E.164 numbers.
     * @return array<int, string>  the subset that are on WhatsApp.
     */
    public function validWhatsAppNumbers(array $phones): array
    {
        $phones = array_values(array_unique(array_filter($phones)));

        if ($phones === []) {
            return [];
        }

        try {
            $response = $this->post('/v1/whatsapp/contacts/check', [
                'accountId' => config('services.wachap.account_id'),
                'phones' => $phones,
            ]);
            $body = $response->json();
        } catch (\Throwable $e) {
            return $phones; // fail-open
        }

        // Tolerant parse: look for a list of results with a phone + a truthy
        // "exists/valid/onWhatsapp" flag; keep the numbers flagged valid.
        $rows = $body['data'] ?? $body['result'] ?? $body['numbers'] ?? $body ?? null;

        if (! is_array($rows)) {
            return $phones;
        }

        $valid = [];
        $recognized = 0; // rows we actually understood (had a phone field)
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $phone = (string) ($row['phone'] ?? $row['number'] ?? $row['phone_number'] ?? '');
            if ($phone === '') {
                continue;
            }
            $recognized++;
            $ok = $row['exists'] ?? $row['valid'] ?? $row['isValid'] ?? $row['onWhatsapp'] ?? $row['on_whatsapp'] ?? null;
            if ($ok === true || $ok === 1 || $ok === '1' || $ok === 'true') {
                $valid[] = $phone;
            }
        }

        // Fail open ONLY when we couldn't interpret the response shape at all.
        // If we understood it (recognized ≥1 row), trust it — even if none valid.
        return $recognized > 0 ? $valid : $phones;
    }

    /**
     * Whether the Wachap credentials are fully configured.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.wachap.base_url'))
            && filled(config('services.wachap.token'))
            && filled(config('services.wachap.account_id'));
    }

    /**
     * Send any message type to the Wachap API.
     *
     * @param  array{to: string, type: string, content: mixed}  $data
     */
    private function send(array $data): Response
    {
        $baseUrl = rtrim((string) config('services.wachap.base_url'), '/');

        return Http::withToken((string) config('services.wachap.token'))
            ->acceptJson()
            ->post($baseUrl.'/v1/whatsapp/messages/send', [
                'data' => array_merge([
                    'accountId' => config('services.wachap.account_id'),
                    'isCampaign' => false,
                ], $data),
            ])
            ->throw();
    }

    /**
     * Low-level authenticated POST to a Wachap endpoint (throws on HTTP error).
     *
     * @param  array<string, mixed>  $body
     */
    private function post(string $path, array $body): Response
    {
        $baseUrl = rtrim((string) config('services.wachap.base_url'), '/');

        return Http::withToken((string) config('services.wachap.token'))
            ->acceptJson()
            ->post($baseUrl.$path, $body)
            ->throw();
    }
}
