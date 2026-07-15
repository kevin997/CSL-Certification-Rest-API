<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thrown when Ollama can't produce a usable response after all retries.
 */
class OllamaException extends \RuntimeException
{
}

/**
 * Chat completions via a self-hosted Ollama server. Used to generate
 * marketing/retention copy (see App\Support\Marketing\Multilingual and
 * App\Models\MarketingMessage).
 */
class OllamaService
{
    /**
     * Whether the Ollama endpoint is configured.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.ollama.url'));
    }

    /**
     * Chat with the model, forcing JSON output, and return the decoded array.
     *
     * Retries once with an explicit "Return ONLY valid JSON." nudge appended
     * to the user message if the first response isn't valid JSON, then once
     * more against the fallback model. Throws after all attempts fail.
     *
     * @throws OllamaException
     */
    public function chatJson(string $system, string $user, ?string $model = null): array
    {
        $model ??= (string) config('services.ollama.model');

        $attempts = [
            [$model, $user],
            [$model, $user."\n\nReturn ONLY valid JSON."],
            [(string) config('services.ollama.fallback_model'), $user."\n\nReturn ONLY valid JSON."],
        ];

        $lastError = null;

        foreach ($attempts as [$attemptModel, $attemptUser]) {
            try {
                $content = $this->chat($system, $attemptUser, $attemptModel, true);
            } catch (\Throwable $e) {
                $lastError = $e;

                continue;
            }

            $decoded = json_decode($content, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            $lastError = new \RuntimeException("Ollama returned invalid JSON: {$content}");
        }

        throw new OllamaException('Ollama chatJson failed after retries', 0, $lastError);
    }

    /**
     * Plain-text chat (no forced JSON format).
     *
     * @throws OllamaException
     */
    public function chatText(string $system, string $user, ?string $model = null): string
    {
        try {
            return $this->chat($system, $user, $model ?? (string) config('services.ollama.model'), false);
        } catch (\Throwable $e) {
            throw new OllamaException('Ollama chatText failed', 0, $e);
        }
    }

    /**
     * Low-level call to /api/chat. Returns message.content.
     */
    private function chat(string $system, string $user, string $model, bool $json): string
    {
        $started = microtime(true);

        $payload = [
            'model' => $model,
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'options' => [
                'num_ctx' => (int) config('services.ollama.num_ctx'),
                'temperature' => 0.8,
            ],
        ];

        if ($json) {
            $payload['format'] = 'json';
        }

        $url = rtrim((string) config('services.ollama.url'), '/');

        $response = Http::timeout((int) config('services.ollama.timeout'))
            ->post($url.'/api/chat', $payload)
            ->throw();

        $content = (string) $response->json('message.content');

        Log::debug('OllamaService chat completed', [
            'model' => $model,
            'json' => $json,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);

        return $content;
    }
}
