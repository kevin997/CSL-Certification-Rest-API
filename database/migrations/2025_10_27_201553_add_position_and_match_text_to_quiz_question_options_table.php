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
        Schema::table('quiz_question_options', function (Blueprint $table) {
            // Add position field for hotspot questions (stores {x: number, y: number, radius: number} as JSON)
            $table->json('position')->nullable()->after('is_correct')->comment('JSON object for hotspot questions (x, y coordinates and radius as percentages)');

            // Add match_text field for matching questions
            $table->text('match_text')->nullable()->after('position')->comment('Match text for matching question types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_question_options', function (Blueprint $table) {
            $table->dropColumn(['position', 'match_text']);
        });
    }
};
