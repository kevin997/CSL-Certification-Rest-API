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
        if (!Schema::hasTable('lesson_question_responses')) {
            Schema::table('lesson_question_responses', function (Blueprint $table) {
                // Add fields for various response types
                $table->json('selected_option_ids')->nullable()->comment('JSON array for multiple-selection questions');
                $table->json('matrix_responses')->nullable()->comment('JSON for matrix question responses');
                $table->json('hotspot_responses')->nullable()->comment('JSON for hotspot question responses');
                $table->json('matching_responses')->nullable()->comment('JSON for matching question responses');
                $table->json('fill_blanks_responses')->nullable()->comment('JSON for fill-in-blanks responses');

                // Add fields for grading
                $table->text('feedback')->nullable()->comment('Feedback for this response');
                $table->foreignId('graded_by')->nullable()->constrained('users')->onDelete('set null')->comment('User ID of the grader');
                $table->timestamp('graded_at')->nullable()->comment('When this response was graded');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_question_responses', function (Blueprint $table) {
            // Remove foreign key constraint first
            $table->dropForeign(['graded_by']);

            // Remove added fields
            $table->dropColumn([
                'selected_option_ids',
                'matrix_responses',
                'hotspot_responses',
                'matching_responses',
                'fill_blanks_responses',
                'feedback',
                'graded_by',
                'graded_at'
            ]);
        });
    }
};
