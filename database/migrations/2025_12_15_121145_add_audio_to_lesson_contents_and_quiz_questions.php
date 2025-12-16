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
            $table->unsignedBigInteger('audio_media_asset_id')->nullable()->after('resources');
            $table->foreign('audio_media_asset_id')->references('id')->on('media_assets')->nullOnDelete();
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->string('stimulus_type')->default('none')->after('question_text');
            $table->unsignedBigInteger('stimulus_media_asset_id')->nullable()->after('stimulus_type');
            $table->foreign('stimulus_media_asset_id')->references('id')->on('media_assets')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_contents', function (Blueprint $table) {
            $table->dropForeign(['audio_media_asset_id']);
            $table->dropColumn('audio_media_asset_id');
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropForeign(['stimulus_media_asset_id']);
            $table->dropColumn(['stimulus_type', 'stimulus_media_asset_id']);
        });
    }
};
