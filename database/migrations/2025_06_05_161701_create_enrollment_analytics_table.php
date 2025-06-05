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
        Schema::create('enrollment_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enrollment_id');
            $table->string('activity_id');
            $table->string('activity_type');
            
            // Time-based metrics
            $table->integer('time_spent')->default(0);         // Total time spent on activity in seconds
            $table->integer('active_time')->default(0);        // Time actively engaged (no idle periods) in seconds
            $table->integer('idle_time')->default(0);          // Time spent idle in seconds
            $table->integer('session_duration')->default(0);   // Duration of current/last session in seconds
            $table->integer('total_sessions')->default(0);     // Number of separate engagement sessions
            $table->integer('average_session_length')->default(0); // Average length of sessions in seconds
            $table->timestamp('first_accessed_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('time_to_completion')->nullable(); // Time from first access to completion in seconds
            
            // Interaction metrics
            $table->integer('click_count')->default(0);
            $table->integer('scroll_count')->default(0);
            $table->integer('scroll_depth')->default(0);       // Maximum scroll depth as percentage (0-100)
            $table->integer('focus_events')->default(0);       // Number of times user focused on activity
            $table->integer('pause_resume_events')->default(0); // Applicable for video/audio content
            $table->integer('retry_attempts')->default(0);     // Number of retry attempts if applicable
            $table->integer('navigation_events')->default(0);  // Navigation within the activity
            
            // Engagement metrics
            $table->float('engagement_score')->default(0);     // 0-100 calculated engagement score
            $table->float('completion_percentage')->default(0); // 0-100 progress through activity
            $table->float('interaction_frequency')->default(0); // Interactions per minute
            
            // Additional metadata
            $table->json('performance_data')->nullable();      // Quiz scores, completion times, etc.
            $table->json('device_info')->nullable();           // Device, browser, etc.
            $table->json('event_log')->nullable();             // Log of significant events during activity
            
            $table->timestamps();
            
            // Indexes
            $table->index('enrollment_id');
            $table->index(['activity_id', 'activity_type']);
            $table->index('engagement_score');
            $table->index('completion_percentage');
            $table->index('first_accessed_at');
            $table->index('completed_at');
            
            // Unique constraint to prevent duplicate analytics entries
            $table->unique(['enrollment_id', 'activity_id', 'activity_type'], 'unique_activity_analytics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollment_analytics');
    }
};
