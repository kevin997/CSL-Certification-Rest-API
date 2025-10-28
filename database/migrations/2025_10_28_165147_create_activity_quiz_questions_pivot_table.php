<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates a pivot table to enable many-to-many relationship between
     * quiz_contents (activities) and quiz_questions, eliminating the need
     * to duplicate questions when importing them across activities.
     */
    public function up(): void
    {
        Schema::create('activity_quiz_questions', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('quiz_content_id')
                ->constrained('quiz_contents')
                ->onDelete('cascade')
                ->comment('The quiz/activity this question belongs to');

            $table->foreignId('quiz_question_id')
                ->constrained('quiz_questions')
                ->onDelete('cascade')
                ->comment('The actual question (shared across activities)');

            // Question order within this specific quiz
            $table->integer('order')->default(0)->comment('Display order of question in this quiz');

            $table->timestamps();

            // Ensure a question can only be added once per quiz
            $table->unique(['quiz_content_id', 'quiz_question_id'], 'unique_question_per_quiz');

            // Indexes for performance
            $table->index(['quiz_content_id', 'order'], 'quiz_content_order_index');
            $table->index('quiz_question_id', 'question_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_quiz_questions');
    }
};
