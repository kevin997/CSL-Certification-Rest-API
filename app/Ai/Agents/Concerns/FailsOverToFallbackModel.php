<?php

namespace App\Ai\Agents\Concerns;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\AgentResponse;

/**
 * Retry an agent turn on the free router when its primary provider throws.
 *
 * Every agent now declares two different providers in #[Provider], which the
 * SDK can fail over between natively. This stays as the outer net: a provider
 * map covers a model that errors, not a key that is missing, a quota that is
 * spent, or a network that is down mid-run.
 *
 * It used to fail over to a second Ollama box. Both boxes are gone, and the
 * fallback pointed at a dead address for as long as they had been — which
 * nothing noticed, because every caller degrades quietly. Do not point this at
 * anything that can disappear without someone being told.
 */
trait FailsOverToFallbackModel
{
    /**
     * OpenRouter's Free Models Router: zero cost, and it picks a free model at
     * random, so treat whatever comes back as lower quality than the primary.
     */
    private const FALLBACK_PROVIDER = 'openrouter';

    private const FALLBACK_MODEL = 'openrouter/free';

    /**
     * Prompt the agent, retrying once on the fallback provider if the primary
     * throws.
     */
    public function promptWithFailover(string $prompt): AgentResponse
    {
        try {
            return $this->prompt($prompt);
        } catch (\Throwable $e) {
            Log::warning(static::class.': primary provider/model failed, retrying on the free router', [
                'fallback_provider' => self::FALLBACK_PROVIDER,
                'fallback_model' => self::FALLBACK_MODEL,
                'error' => $e->getMessage(),
            ]);

            return $this->prompt($prompt, provider: self::FALLBACK_PROVIDER, model: self::FALLBACK_MODEL);
        }
    }
}
