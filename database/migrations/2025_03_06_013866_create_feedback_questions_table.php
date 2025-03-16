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
        Schema::create('feedback_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_content_id')->constrained()->onDelete('cascade');
            $table->text('question_text');
            $table->string('question_type'); // text, textarea, radio, checkbox, select, rating, scale
            $table->json('options')->nullable(); // JSON array of options for radio, checkbox, select
            $table->boolean('is_required')->default(true);
            $table->integer('order')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('feedback_content_id');
            $table->index('question_type');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_questions');
    }
};
