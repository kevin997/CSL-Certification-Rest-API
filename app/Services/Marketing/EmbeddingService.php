<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;

/**
 * Thin wrapper around the Laravel AI SDK's embeddings support.
 *
 * There is no embeddings provider configured. Ollama hosted nomic-embed-text
 * until both boxes were decommissioned, and nothing replaced it, so embed()
 * returns null and every caller degrades: IndexKnowledgeCommand indexes
 * nothing, SearchKnowledgeBase finds nothing, and the marketing generator
 * writes without retrieval. Set AI_EMBEDDINGS_PROVIDER and that provider's key
 * to bring it back — the rest of this class already handles it.
 * The gateway shipped with laravel/ai already implements
 * EmbeddingProvider/EmbeddingGateway and posts batched inputs straight to
 * the provider's native embeddings endpoint, so no direct HTTP client is needed
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
                ->generate(self::provider(), self::model());
        } catch (\Throwable $e) {
            Log::warning('EmbeddingService: failed generating embeddings: '.$e->getMessage());

            return null;
        }

        return $response->embeddings;
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

    private static function provider(): string
    {
        return (string) config('ai.default_for_embeddings');
    }

    private static function model(): string
    {
        return (string) config('ai.embeddings_model', self::MODEL);
    }

    /**
     * Whether an embeddings provider has credentials. False today: nothing
     * replaced Ollama, and a provider without a key would cost one doomed
     * request per batch before failing open.
     */
    public static function isConfigured(): bool
    {
        $provider = self::provider();

        return $provider !== '' && filled(config("ai.providers.{$provider}.key"));
    }
}
