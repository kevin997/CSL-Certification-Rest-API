<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates a table for feedback question options, supporting both legacy
     * question types (multiple_choice, checkbox, dropdown) and new questionnaire
     * type with subquestions and answer option assignments.
     */
    public function up(): void
    {
        Schema::create('feedback_question_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('feedback_question_id')
                ->constrained('feedback_questions')
                ->onDelete('cascade')
                ->comment('The feedback question this option belongs to');

            $table->text('option_text')->comment('The option text displayed to respondents');

            // Questionnaire-specific fields (nullable for legacy question types)
            $table->longText('subquestion_text')->nullable()
                ->comment('For questionnaire type: the subquestion text');
            $table->integer('answer_option_id')->nullable()
                ->comment('For questionnaire type: links to answer option in answer_options JSON array');
            $table->integer('points')->nullable()->default(0)
                ->comment('Points assigned to this option (for scoring)');

            $table->integer('order')->default(0)->comment('Display order');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['feedback_question_id', 'order'], 'feedback_question_order_index');
            $table->index('answer_option_id', 'answer_option_id_index');
            // Note: subquestion_text is LONGTEXT and cannot be indexed without key length
            // The compound index above is sufficient for query performance
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_question_options');
    }
};
