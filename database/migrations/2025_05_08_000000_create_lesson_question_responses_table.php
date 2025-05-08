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
        Schema::create('lesson_question_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('lesson_question_id')->constrained()->onDelete('cascade');
            $table->foreignId('lesson_content_id')->constrained()->onDelete('cascade');
            $table->foreignId('selected_option_id')->nullable()->constrained('lesson_question_options')->onDelete('set null');
            $table->text('text_response')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->float('points_earned')->default(0);
            $table->integer('attempt_number')->default(1);
            $table->timestamps();
            
            // Unique constraint to prevent duplicate responses for the same question and attempt
            $table->unique(['user_id', 'lesson_question_id', 'attempt_number'], 'unique_lesson_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_question_responses');
    }
};
