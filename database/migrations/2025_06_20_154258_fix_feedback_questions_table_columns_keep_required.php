<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration fixes issues with the feedback_questions table:
     * 1. Ensures the question_type column exists with the correct type
     * 2. Ensures the required column exists (not 'is_required')
     * 3. Removes the title column if it exists (as it's not used)
     */
    public function up(): void
    {
        Schema::table('feedback_questions', function (Blueprint $table) {
            // Check if question_type column exists, if not add it
            if (!MigrationHelper::columnExists('feedback_questions', 'question_type')) {
                $table->string('question_type')->after('question_text')->comment('text, rating, multiple_choice, checkbox, dropdown');
            }
            
            // Check if we need to fix the is_required/required column issue
            if (MigrationHelper::columnExists('feedback_questions', 'is_required') && 
                !MigrationHelper::columnExists('feedback_questions', 'required')) {
                // Rename is_required to required
                $table->renameColumn('is_required', 'required');
            }
            
            // If required doesn't exist but is_required doesn't either, create required
            if (!MigrationHelper::columnExists('feedback_questions', 'required') && 
                !MigrationHelper::columnExists('feedback_questions', 'is_required')) {
                $table->boolean('required')->default(true)->after('options');
            }
            
            // Remove title column if it exists (it's not used in the application)
            if (MigrationHelper::columnExists('feedback_questions', 'title')) {
                $table->dropColumn('title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is fixing issues, so down() should be a no-op
        // as reverting these changes would break the application
    }
};
