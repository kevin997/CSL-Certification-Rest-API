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
        if (!Schema::hasTable('lesson_questions')) {

            Schema::table('lesson_questions', function (Blueprint $table) {
                // Add missing fields for various question types
                $table->text('explanation')->nullable()->comment('Explanation of the correct answer');
                $table->text('question_text')->nullable()->comment('Additional text for the question');
                $table->string('title')->nullable()->comment('Optional title for the question');
                $table->string('image_url')->nullable()->comment('URL to image used for questions like matching or hotspot');
                $table->string('image_alt')->nullable()->comment('Alt text description for the image');
                $table->json('blanks')->nullable()->comment('JSON array for fill in blanks questions');
                $table->json('matrix_rows')->nullable()->comment('JSON array for matrix questions');
                $table->json('matrix_columns')->nullable()->comment('JSON array for matrix questions');
                $table->json('matrix_options')->nullable()->comment('JSON array for matrix question options');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_questions', function (Blueprint $table) {
            // Remove added fields
            $table->dropColumn([
                'explanation',
                'question_text',
                'title',
                'image_url',
                'image_alt',
                'blanks',
                'matrix_rows',
                'matrix_columns',
                'matrix_options'
            ]);
        });
    }
};
