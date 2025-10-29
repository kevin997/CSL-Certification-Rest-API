<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds answer_options field to support questionnaire-type feedback questions.
     * This field stores the global answer options (columns) for matrix-style questions.
     */
    public function up(): void
    {
        Schema::table('feedback_questions', function (Blueprint $table) {
            $table->json('answer_options')->nullable()->after('options')
                ->comment('For questionnaire type: array of global answer options (e.g., Strongly Agree, Agree, etc.)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback_questions', function (Blueprint $table) {
            $table->dropColumn('answer_options');
        });
    }
};
