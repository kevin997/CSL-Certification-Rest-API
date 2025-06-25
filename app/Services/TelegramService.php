<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Send message to a Telegram chat via Bot API.
     */
    public function sendMessage(string $chatId, string $text, ?array $button = null): void
    {
        $token = config('services.telegram.bot_token');

        if (!$token) {
            Log::warning('Telegram bot token is not configured.');
            return;
        }

        $payload = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($button) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => [[$button]],
            ]);
        }

        $response = Http::baseUrl("https://api.telegram.org/bot{$token}")
            ->post('sendMessage', $payload);

        if (!$response->ok()) {
            Log::error('Failed to send Telegram message', [
                'response' => $response->body(),
            ]);
        }
    }

    /**
     * Get default chat id from config.
     */
    public function getChatId(): ?string
    {
        return config('services.telegram.chat_id');
    }
}
