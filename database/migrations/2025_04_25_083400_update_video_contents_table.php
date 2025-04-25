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
        Schema::table('video_contents', function (Blueprint $table) {
            // Add missing columns that are validated in the controller
            $table->string('title')->after('id');
            $table->text('description')->nullable()->after('title');
            $table->renameColumn('url', 'video_url');
            $table->renameColumn('provider', 'video_type');
            $table->string('thumbnail_url')->nullable()->after('duration');
            $table->string('captions_url')->nullable()->after('transcript');
            $table->foreignId('activity_id')->after('id')->constrained('activities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_contents', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn([
                'activity_id',
                'title',
                'description',
                'thumbnail_url',
                'captions_url'
            ]);
            $table->renameColumn('video_url', 'url');
            $table->renameColumn('video_type', 'provider');
        });
    }
};
