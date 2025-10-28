<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Populates the activity_quiz_questions pivot table from existing
     * quiz_questions records that have a direct quiz_content_id foreign key.
     * This maintains backward compatibility while enabling the new pivot approach.
     */
    public function up(): void
    {
        // Get all existing quiz questions with their quiz_content_id
        $questions = DB::table('quiz_questions')
            ->whereNull('deleted_at')
            ->select('id', 'quiz_content_id', 'order')
            ->get();

        // Prepare pivot records
        $pivotRecords = [];

        foreach ($questions as $question) {
            $pivotRecords[] = [
                'quiz_content_id' => $question->quiz_content_id,
                'quiz_question_id' => $question->id,
                'order' => $question->order ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert all pivot records in bulk for performance
        if (!empty($pivotRecords)) {
            // Insert in chunks to avoid memory issues
            $chunks = array_chunk($pivotRecords, 500);

            foreach ($chunks as $chunk) {
                DB::table('activity_quiz_questions')->insert($chunk);
            }

            echo "Successfully migrated " . count($pivotRecords) . " question-activity relationships to pivot table.\n";
        } else {
            echo "No questions found to migrate.\n";
        }
    }

    /**
     * Reverse the migrations.
     *
     * Removes all pivot records created by this migration.
     * Note: This does NOT restore the original quiz_content_id values in quiz_questions.
     */
    public function down(): void
    {
        DB::table('activity_quiz_questions')->truncate();
        echo "Pivot table cleared.\n";
    }
};
