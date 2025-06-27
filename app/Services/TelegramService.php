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

    public function sendMessage(string $chatId, string $message, array $buttons): bool
    {
        $message = '{

                    "chat_id": "' . $chatId . '",

                    "text": "' . $message . '",

                    "parse_mode": "Markdown"
                }';

        Log::info($message);

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
                CURLOPT_POSTFIELDS => $message,
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
            return !empty($responseData['ok']);
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram message: ' . $e->getMessage());
            return false;
        }
    }
}
