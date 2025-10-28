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
        Schema::table('lesson_question_options', function (Blueprint $table) {
            // Check if columns already exist before adding them
            if (!Schema::hasColumn('lesson_question_options', 'match_text') && !Schema::hasColumn('lesson_question_options', 'position')) {
                // Add fields for matching questions
                $table->text('match_text')->nullable()->comment('Text to match in matching questions');
                
                // Add position field for hotspot questions
                $table->json('position')->nullable()->comment('JSON object with x,y coordinates and radius for hotspot questions');
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
        Schema::table('lesson_question_options', function (Blueprint $table) {
            // Check if columns exist before removing them
            if (Schema::hasColumn('lesson_question_options', 'match_text') && Schema::hasColumn('lesson_question_options', 'position')) {
                // Remove added fields
                $table->dropColumn([
                    'match_text',
                    'position'
                ]);
            } else {
                echo "Columns match_text and/or position do not exist in lesson_question_options table, skipping...\n";
            }
        });
    }
};
