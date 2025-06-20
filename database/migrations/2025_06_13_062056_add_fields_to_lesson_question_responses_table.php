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
        Schema::table('lesson_question_responses', function (Blueprint $table) {
            // Check if columns already exist before adding them
            if (!Schema::hasColumn('lesson_question_responses', 'selected_option_ids') && !Schema::hasColumn('lesson_question_responses', 'matrix_responses') && !Schema::hasColumn('lesson_question_responses', 'hotspot_responses') && !Schema::hasColumn('lesson_question_responses', 'matching_responses') && !Schema::hasColumn('lesson_question_responses', 'fill_blanks_responses') && !Schema::hasColumn('lesson_question_responses', 'feedback') && !Schema::hasColumn('lesson_question_responses', 'graded_by') && !Schema::hasColumn('lesson_question_responses', 'graded_at')) {
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
            } else {
                echo "Columns already exists.";
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_question_responses', function (Blueprint $table) {
            
            // Check if columns exist before removing them
            if (Schema::hasColumn('lesson_question_responses', 'selected_option_ids') && Schema::hasColumn('lesson_question_responses', 'matrix_responses') && Schema::hasColumn('lesson_question_responses', 'hotspot_responses') && Schema::hasColumn('lesson_question_responses', 'matching_responses') && Schema::hasColumn('lesson_question_responses', 'fill_blanks_responses') && Schema::hasColumn('lesson_question_responses', 'feedback') && Schema::hasColumn('lesson_question_responses', 'graded_by') && Schema::hasColumn('lesson_question_responses', 'graded_at')) {
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
            } else {
                echo "Columns selected_option_ids and/or matrix_responses and/or hotspot_responses and/or matching_responses and/or fill_blanks_responses and/or feedback and/or graded_by and/or graded_at do not exist in lesson_question_responses table, skipping...\n";
            }
        });
    }
};
