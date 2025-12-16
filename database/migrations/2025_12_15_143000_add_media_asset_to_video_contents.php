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
            if (!Schema::hasColumn('video_contents', 'media_asset_id')) {
                $table->unsignedBigInteger('media_asset_id')->nullable()->after('video_url');
                $table->foreign('media_asset_id')->references('id')->on('media_assets')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_contents', function (Blueprint $table) {
            if (Schema::hasColumn('video_contents', 'media_asset_id')) {
                $table->dropForeign(['media_asset_id']);
                $table->dropColumn('media_asset_id');
            }
        });
    }
};
