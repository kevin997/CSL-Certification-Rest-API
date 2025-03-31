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
        Schema::create('assignment_contents', function (Blueprint $table) {
            $table->id();
            $table->text('instructions');
            $table->timestamp('due_date')->nullable();
            $table->integer('passing_score')->default(70); // Percentage
            $table->integer('max_attempts')->nullable(); // null means unlimited
            $table->boolean('allow_late_submissions')->default(false);
            $table->integer('late_submission_penalty')->default(0); // Percentage penalty
            $table->boolean('enable_feedback')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_contents');
    }
};
