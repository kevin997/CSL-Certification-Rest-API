<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quiz_contents', function (Blueprint $table) {
            $table->foreignId('audio_media_asset_id')
                ->nullable()
                ->after('instruction_format')
                ->constrained('media_assets')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_contents', function (Blueprint $table) {
            $table->dropForeign(['audio_media_asset_id']);
            $table->dropColumn('audio_media_asset_id');
        });
    }
};
