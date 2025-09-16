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
        // Create chat participation analytics table
        Schema::create('chat_participation_analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('course_id');
            $table->uuid('environment_id');
            $table->integer('message_count')->default(0);
            $table->integer('active_days')->default(0);
            $table->integer('engagement_score')->default(0);
            $table->date('first_message_date')->nullable();
            $table->timestamp('last_activity_date')->nullable();
            $table->boolean('certificate_generated')->default(false);
            $table->string('certificate_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'engagement_score']);
            $table->index(['certificate_generated']);
            $table->index(['environment_id']);
            $table->index(['last_activity_date']);
        });

        // Create course engagement analytics table
        Schema::create('course_engagement_analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('environment_id');
            $table->date('date');
            $table->integer('total_messages')->default(0);
            $table->integer('unique_participants')->default(0);
            $table->decimal('average_response_time', 8, 2)->nullable();
            $table->decimal('instructor_participation_rate', 5, 2)->nullable();
            $table->decimal('engagement_score', 5, 2)->nullable();
            $table->integer('peak_activity_hour')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['course_id', 'date']);
            $table->index(['date']);
            $table->index(['environment_id']);
            $table->index(['engagement_score']);
            $table->index(['course_id', 'environment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_engagement_analytics');
        Schema::dropIfExists('chat_participation_analytics');
    }
};
