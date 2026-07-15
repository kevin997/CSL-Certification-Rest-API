<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores an embedding vector (nomic-embed-text, Ollama) for each generated
 * marketing message so GenerateMarketingContentCommand can semantically
 * dedupe new candidates against recent same-channel messages instead of
 * relying solely on exact-hash matching.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! MigrationHelper::tableExists('marketing_messages')) {
            // Table doesn't exist, skip this migration
            return;
        }

        if (! MigrationHelper::columnExists('marketing_messages', 'embedding')) {
            Schema::table('marketing_messages', function (Blueprint $table) {
                $table->json('embedding')->nullable()->after('source');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (MigrationHelper::columnExists('marketing_messages', 'embedding')) {
            Schema::table('marketing_messages', function (Blueprint $table) {
                $table->dropColumn('embedding');
            });
        }
    }
};
