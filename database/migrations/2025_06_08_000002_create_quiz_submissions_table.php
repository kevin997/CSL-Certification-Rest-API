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
        if (!Schema::hasTable('quiz_submissions')) {
        Schema::create('quiz_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quiz_content_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->float('score')->default(0);
            $table->float('max_score')->default(0);
            $table->float('percentage_score')->default(0);
            $table->boolean('is_passed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->integer('time_spent')->default(0); // Time spent in seconds
            $table->integer('attempt_number')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
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
        if (Schema::hasTable('quiz_submissions')) {
            Schema::dropIfExists('quiz_submissions');
        }
    }
};
