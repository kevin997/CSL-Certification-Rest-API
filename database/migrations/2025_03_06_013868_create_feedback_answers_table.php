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
        Schema::create('feedback_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_submission_id')->constrained()->onDelete('cascade');
            $table->foreignId('feedback_question_id')->constrained()->onDelete('cascade');
            $table->text('answer_text')->nullable();
            $table->float('answer_value')->nullable(); // For numeric ratings/scales
            $table->json('answer_options')->nullable(); // JSON array for checkbox selections
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('feedback_submission_id');
            $table->index('feedback_question_id');
            
            // Unique constraint to prevent duplicate answers for the same question in a submission
            $table->unique(['feedback_submission_id', 'feedback_question_id'], 'unique_feedback_answer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_answers');
    }
};
