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
        Schema::create('assessment_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_submission_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('violation_type', ['tab_switch', 'window_blur', 'fullscreen_exit', 'right_click', 'copy_paste', 'devtools_open']);
            $table->timestamp('violated_at');
            $table->integer('question_index')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['quiz_submission_id', 'violation_type']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_violations');
    }
};
