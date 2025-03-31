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
        Schema::create('activity_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->onDelete('cascade');
            $table->foreignId('activity_id')->constrained()->onDelete('cascade');
            $table->timestamp('completed_at')->nullable();
            $table->float('score')->nullable();
            $table->integer('time_spent')->default(0); // in seconds
            $table->integer('attempts')->default(1);
            $table->string('status')->default('started'); // started, in-progress, completed, failed
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['enrollment_id', 'activity_id']);
            $table->index('status');
            $table->index('completed_at');
            
            // Unique constraint to prevent duplicate completions
            $table->unique(['enrollment_id', 'activity_id'], 'unique_activity_completion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_completions');
    }
};
