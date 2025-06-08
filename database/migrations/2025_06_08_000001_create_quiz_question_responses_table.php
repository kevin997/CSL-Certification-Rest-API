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
        if (!Schema::hasTable('quiz_question_responses')) {
            Schema::create('quiz_question_responses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('quiz_submission_id');
                $table->unsignedBigInteger('quiz_question_id');
                $table->json('user_response')->nullable(); // Stores user's selected answer(s)
                $table->boolean('is_correct')->default(false);
                $table->float('points_earned')->default(0);
                $table->float('max_points')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('quiz_question_responses')) {
            Schema::dropIfExists('quiz_question_responses');
        }
    }
};
