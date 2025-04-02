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
            // Add the missing columns that our frontend expects
            $table->foreignId('activity_id')->after('id')->constrained('activities')->onDelete('cascade');
            $table->string('title')->after('activity_id');
            $table->text('description')->nullable()->after('title');
            $table->text('content')->nullable()->after('description');
            $table->enum('format', ['plain', 'markdown', 'html', 'wysiwyg'])->default('markdown')->after('content');
            $table->integer('estimated_duration')->nullable()->after('format');
            $table->json('resources')->nullable()->after('estimated_duration');
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
