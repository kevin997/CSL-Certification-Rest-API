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
}
