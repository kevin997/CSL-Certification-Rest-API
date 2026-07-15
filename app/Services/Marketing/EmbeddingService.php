<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;

/**
 * Thin wrapper around the Laravel AI SDK's Ollama embeddings support
 * (nomic-embed-text on the CSL Ollama box — config('ai.providers.ollama.url')).
 * The Ollama gateway shipped with laravel/ai already implements
 * EmbeddingProvider/EmbeddingGateway and posts batched inputs straight to
 * Ollama's native `/api/embed` endpoint, so no direct HTTP client is needed
 * here.
 *
 * Fails open everywhere: a down/misconfigured embeddings backend must never
 * break marketing content generation or knowledge indexing — callers should
 * treat a null return as "skip the semantic feature for this run" and keep
 * going with hash-only dedupe / excerpt-only grounding.
 */
class EmbeddingService
{
    private const MODEL = 'nomic-embed-text';

    private const TIMEOUT = 60;

    /**
     * Get embedding vectors for the given texts.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<float>>|null  null on ANY failure (fail-open)
     */
    public function embed(array $texts): ?array
    {
        if (! self::isConfigured() || empty($texts)) {
            return null;
        }

        try {
            $response = Embeddings::for($texts)
                ->timeout(self::TIMEOUT)
                ->generate('ollama', self::MODEL);

            return $response->embeddings;
        } catch (\Throwable $e) {
            Log::warning('EmbeddingService: failed generating embeddings: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Cosine similarity between two embedding vectors.
     */
    public static function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * ($b[$i] ?? 0.0);
            $normA += $value * $value;
        }

        foreach ($b as $value) {
            $normB += $value * $value;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Whether the Ollama embeddings backend is configured.
     */
    public static function isConfigured(): bool
    {
        return filled(config('ai.providers.ollama.url'));
    }
}
