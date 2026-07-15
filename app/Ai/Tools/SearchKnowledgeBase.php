<?php

namespace App\Ai\Tools;

use App\Models\ContentChunk;
use App\Services\Marketing\EmbeddingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * RAG tool over the KURSA knowledge base (`content_chunks` — product tutorials
 * + platform docs, embedded via App\Services\Marketing\EmbeddingService). Used
 * by InstructorAssistantAgent to ground answers instead of letting the model
 * invent platform behaviour.
 */
class SearchKnowledgeBase implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search the KURSA knowledge base (product tutorials and platform '.
            'documentation) for passages relevant to a question about using the '.
            'platform.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = $request->string('query')->toString();

        $embeddings = app(EmbeddingService::class)->embed([$query]);

        if (! $embeddings) {
            return 'Knowledge base unavailable.';
        }

        $queryEmbedding = $embeddings[0];

        $chunks = ContentChunk::whereNotNull('embedding')
            ->get(['id', 'title', 'url', 'content', 'embedding']);

        if ($chunks->isEmpty()) {
            return 'Knowledge base unavailable.';
        }

        $ranked = $chunks
            ->map(fn (ContentChunk $chunk) => [
                'chunk' => $chunk,
                'score' => EmbeddingService::cosine($queryEmbedding, $chunk->embedding),
            ])
            ->sortByDesc('score')
            ->take(4);

        return $ranked
            ->map(function (array $entry) {
                $chunk = $entry['chunk'];

                return "[{$chunk->title}] ({$chunk->url})\n".
                    substr($chunk->content, 0, 900);
            })
            ->implode("\n---\n");
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The instructor question to search the knowledge base for.')
                ->required(),
        ];
    }

    /**
     * Get the tool's name as exposed to the model.
     */
    public function name(): string
    {
        return 'search_knowledge_base';
    }
}
