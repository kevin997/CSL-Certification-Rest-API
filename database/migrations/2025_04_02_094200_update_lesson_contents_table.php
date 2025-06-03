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
        Schema::table('lesson_contents', function (Blueprint $table) {
            // Add the missing columns that our frontend expects
            // Check if column already exists

            if (!MigrationHelper::columnExists('lesson_contents', 'activity_id')) {

                $table->foreignId('activity_id')->after('id')->constrained('activities')->onDelete('cascade');
                // Check if column already exists

                if (!MigrationHelper::columnExists('lesson_contents', 'title')) {

                    $table->string('title')->after('activity_id');
                    // Check if column already exists

                    if (!MigrationHelper::columnExists('lesson_contents', 'description')) {

                        $table->text('description')->nullable()->after('title');
                        // Check if column already exists

                        if (!MigrationHelper::columnExists('lesson_contents', 'content')) {

                            $table->text('content')->nullable()->after('description');
                            $table->enum('format', ['plain', 'markdown', 'html', 'wysiwyg'])->default('markdown')->after('content');
                            // Check if column already exists

                            if (!MigrationHelper::columnExists('lesson_contents', 'estimated_duration')) {

                                $table->integer('estimated_duration')->nullable()->after('format');
                                $table->json('resources')->nullable()->after('estimated_duration');
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_contents', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn([
                'activity_id',
                'title',
                'description',
                'content',
                'format',
                'estimated_duration',
                'resources'
            ]);
        });
    }
};
