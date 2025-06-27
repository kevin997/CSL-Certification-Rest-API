<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function getChatId(): ?string
    {
        return "-1001836815830";
    }
    
    /**
     * Ensure URL is valid for Telegram buttons.
     * Telegram doesn't allow localhost URLs, so we need to convert them to a valid format.
     *
     * @param string $url
     * @return string
     */
    private function ensureValidTelegramUrl(string $url): string
    {
        // Check if URL contains localhost or 127.0.0.1
        if (strpos($url, 'localhost') !== false || strpos($url, '127.0.0.1') !== false) {
            // For development/testing, we'll use a placeholder domain that redirects properly
            // In a real-world scenario, you might want to use a URL shortener service or a proper domain
            
            // Option 1: Use a service like ngrok or localtunnel for local development
            // Option 2: Use a placeholder that clearly indicates it's a development URL
            
            // For now, we'll use example.com as a placeholder
            // In production, you should replace this with your actual domain
            $url = str_replace(['http://localhost', 'http://127.0.0.1'], 'https://example.com', $url);
            
            // Log that we've modified the URL for Telegram compatibility
            Log::info('Modified localhost URL for Telegram button compatibility', [
                'original_url' => $url,
                'modified_url' => $url
            ]);
        }
        
        return $url;
    }

    public function sendMessage(string $chatId, string $message, array $buttons): bool
    {
        // Create payload as array and then JSON encode it properly
        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'MarkdownV2'
        ];
        
        // Add buttons if provided
        if (!empty($buttons)) {
            $inlineKeyboard = [];
            
            // Handle both single button and array of buttons
            if (isset($buttons['text']) && isset($buttons['url'])) {
                // Single button format
                $buttonUrl = $this->ensureValidTelegramUrl($buttons['url']);
                $inlineKeyboard[] = [['text' => $buttons['text'], 'url' => $buttonUrl]];
            } else {
                // Multiple buttons format
                foreach ($buttons as $button) {
                    if (isset($button['text']) && isset($button['url'])) {
                        $buttonUrl = $this->ensureValidTelegramUrl($button['url']);
                        $inlineKeyboard[] = [['text' => $button['text'], 'url' => $buttonUrl]];
                    }
                }
            }
            
            if (!empty($inlineKeyboard)) {
                $payload['reply_markup'] = [
                    'inline_keyboard' => $inlineKeyboard
                ];
            }
        }
        
        // Convert to JSON with proper encoding
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Log the payload for debugging
        Log::info('Sending Telegram message', ['payload' => $payload]);

        try {
            $botToken = config('services.telegram.bot_token');
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.telegram.org/bot2113199011:{$botToken}/sendMessage",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                Log::error('Failed to send Telegram message: ' . $err);
                return false;
            }

            $responseData = json_decode($response, true);
            
            // Log response for debugging
            if (empty($responseData['ok'])) {
                Log::error('Telegram API error', [
                    'response' => $responseData,
                    'message' => $message
                ]);
            }
            
            return !empty($responseData['ok']);
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram message: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Escape special characters for Telegram's MarkdownV2 format.
     * 
     * @param string $text
     * @return string
     */
    public function escapeMarkdownV2(string $text): string
    {
        // Characters that need to be escaped in MarkdownV2:
        // '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

        foreach ($specialChars as $char) {
            $text = str_replace($char, "\\{$char}", $text);
        }

        return $text;
    }
}
