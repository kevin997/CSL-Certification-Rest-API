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
        Schema::create('lesson_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_content_id')->constrained()->onDelete('cascade');
            $table->foreignId('content_part_id')->nullable()->constrained('lesson_content_parts')->onDelete('set null');
            $table->text('question');
            $table->string('question_type'); // multiple_choice, true_false, short_answer, etc.
            $table->boolean('is_scorable')->default(false);
            $table->integer('points')->default(1);
            $table->integer('order')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['lesson_content_id', 'order']);
            $table->index('content_part_id');
            $table->index('question_type');
            $table->index('is_scorable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_questions');
    }
};
