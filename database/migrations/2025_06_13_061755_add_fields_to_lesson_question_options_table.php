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
        if (!Schema::hasTable('lesson_question_options')) {
            Schema::table('lesson_question_options', function (Blueprint $table) {
                // Add fields for matching questions
                $table->text('match_text')->nullable()->comment('Text to match in matching questions');

                // Add position field for hotspot questions
                $table->json('position')->nullable()->comment('JSON object with x,y coordinates for hotspot questions');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_question_options', function (Blueprint $table) {
            // Remove added fields
            $table->dropColumn([
                'match_text',
                'position'
            ]);
        });
    }
};
