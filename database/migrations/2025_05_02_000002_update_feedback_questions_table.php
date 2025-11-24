<?php

use App\Helpers\MigrationHelper;
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
        Schema::table('feedback_questions', function (Blueprint $table) {
            // Rename fields to match frontend implementation
            $table->renameColumn('is_required', 'required');

            // Update question_type enum values to match frontend
            // This requires dropping and recreating the column
            $table->dropIndex('feedback_questions_question_type_index');
            $table->dropColumn('question_type');
            // Check if column already exists

            if (!MigrationHelper::columnExists('feedback_questions', 'question_type')) {

                // Check if column already exists


                if (!MigrationHelper::columnExists('feedback_questions', 'question_type')) {


                    $table->string('question_type')->after('question_text'); // Will add: text, rating, multiple_choice, checkbox, dropdown

                    // Add title field
                    // Check if column already exists

                    if (!MigrationHelper::columnExists('feedback_questions', 'title')) {

                        $table->string('title')->after('feedback_content_id');
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback_questions', function (Blueprint $table) {
            // Rename fields back to original
            $table->renameColumn('required', 'is_required');

            // Revert question_type changes
            $table->dropColumn('question_type');
            $table->string('question_type')->after('question_text'); // Original: text, textarea, radio, checkbox, select, rating, scale

            // Remove added fields
            $table->dropColumn('title');
        });
    }
};
