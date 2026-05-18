<?php

use App\Helpers\MigrationHelper;
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
            if (!MigrationHelper::columnExists('video_contents', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('duration');
            }

            if (!Schema::hasIndex('video_contents', 'video_contents_activity_sort_index')) {
                $table->index(['activity_id', 'sort_order'], 'video_contents_activity_sort_index');
            }
        });

        Schema::table('activity_completions', function (Blueprint $table) {
            if (!MigrationHelper::columnExists('activity_completions', 'progress_percentage')) {
                $table->float('progress_percentage')->default(0)->after('status');
            }

            if (!MigrationHelper::columnExists('activity_completions', 'completion_data')) {
                $table->json('completion_data')->nullable()->after('progress_percentage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_completions', function (Blueprint $table) {
            if (MigrationHelper::columnExists('activity_completions', 'completion_data')) {
                $table->dropColumn('completion_data');
            }

            if (MigrationHelper::columnExists('activity_completions', 'progress_percentage')) {
                $table->dropColumn('progress_percentage');
            }
        });

        Schema::table('video_contents', function (Blueprint $table) {
            if (Schema::hasIndex('video_contents', 'video_contents_activity_sort_index')) {
                $table->dropIndex('video_contents_activity_sort_index');
            }

            if (MigrationHelper::columnExists('video_contents', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
