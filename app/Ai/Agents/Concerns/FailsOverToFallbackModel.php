<?php

namespace App\Ai\Agents\Concerns;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\AgentResponse;

/**
 * The Laravel AI SDK (v0.7.2) only lets model failover be declared
 * natively across DIFFERENT providers — the `#[Provider]` attribute (or a
 * `provider()` method) accepts a provider => model map, but PHP arrays can't
 * hold the same provider key twice, so two models on the SAME provider (e.g.
 * two Ollama models) can't be expressed that way. This concern implements
 * the same "try the primary model, fall back to a smaller model on failure"
 * behaviour manually.
 */
trait FailsOverToFallbackModel
{
    /**
     * The smaller/faster model to retry against when the primary model
     * throws (both are hosted on the same Ollama box).
     */
    private const FALLBACK_MODEL = 'llama3.2:1b';

    /**
     * Prompt the agent, retrying once against the fallback model if the
     * primary model throws.
     */
    public function promptWithFailover(string $prompt): AgentResponse
    {
        try {
            return $this->prompt($prompt);
        } catch (\Throwable $e) {
            Log::warning(static::class.': primary model failed, retrying with fallback model', [
                'fallback_model' => self::FALLBACK_MODEL,
                'error' => $e->getMessage(),
            ]);

            return $this->prompt($prompt, model: self::FALLBACK_MODEL);
        }
    }
}
