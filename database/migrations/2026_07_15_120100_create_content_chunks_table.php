<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retrieval-grounding knowledge base: chunked full-text content (blog posts,
 * platform docs) with embeddings (nomic-embed-text via Ollama), indexed by
 * kursa:index-knowledge and read by GenerateMarketingContentCommand to
 * ground generation in the real article/doc text instead of a thin excerpt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_chunks', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 24); // blog | doc
            $table->string('source_id', 64); // WP post id or doc relative path
            $table->string('url')->nullable();
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('chunk_index')->default(0);
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->string('hash', 64)->unique(); // sha256 of source_type|source_id|chunk_index|content

            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_chunks');
    }
};
