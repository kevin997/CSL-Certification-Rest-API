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
        Schema::table('lesson_contents', function (Blueprint $table) {
            // Add video_media_asset_id column and foreign key
            if (!Schema::hasColumn('lesson_contents', 'video_media_asset_id')) {
                $table->unsignedBigInteger('video_media_asset_id')->nullable()->after('audio_media_asset_id');
                $table->foreign('video_media_asset_id')->references('id')->on('media_assets')->nullOnDelete();
            }

            // Add video_url column for backward compatibility
            if (!Schema::hasColumn('lesson_contents', 'video_url')) {
                $table->string('video_url')->nullable()->after('video_media_asset_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_contents', function (Blueprint $table) {
            if (Schema::hasColumn('lesson_contents', 'video_media_asset_id')) {
                 $table->dropForeign(['video_media_asset_id']);
                 $table->dropColumn('video_media_asset_id');
            }
            if (Schema::hasColumn('lesson_contents', 'video_url')) {
                $table->dropColumn('video_url');
            }
        });
    }
};
