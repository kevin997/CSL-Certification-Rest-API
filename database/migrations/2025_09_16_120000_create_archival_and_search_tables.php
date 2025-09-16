<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create archived chat messages table
        Schema::create('archived_chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('environment_id')->nullable();
            $table->string('archive_path', 500); // S3 path can be long
            $table->integer('message_count')->unsigned();
            $table->decimal('storage_size_mb', 10, 3)->unsigned();
            $table->timestamp('archived_date');
            $table->timestamp('start_date'); // First message date in archive
            $table->timestamp('end_date');   // Last message date in archive
            $table->string('checksum', 32)->nullable(); // MD5 checksum for integrity
            $table->integer('batch_index')->unsigned()->nullable();
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['course_id', 'start_date', 'end_date'], 'archived_messages_course_dates');
            $table->index(['environment_id', 'archived_date'], 'archived_messages_env_date');
            $table->index(['archived_date']);
            $table->index(['course_id', 'archived_date']);

            // Foreign key constraints
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('environment_id')->references('id')->on('environments')->onDelete('set null');
        });

        // Create archival jobs table
        Schema::create('archival_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('environment_id')->nullable();
            $table->timestamp('cutoff_date');
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->decimal('progress', 5, 2)->default(0); // 0.00 to 100.00
            $table->integer('messages_archived')->unsigned()->default(0);
            $table->decimal('storage_size_mb', 10, 3)->unsigned()->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for monitoring and querying
            $table->index(['course_id', 'status'], 'archival_jobs_course_status');
            $table->index(['environment_id', 'status'], 'archival_jobs_env_status');
            $table->index(['started_at']);
            $table->index(['status', 'started_at'], 'archival_jobs_status_started');

            // Foreign key constraints
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('environment_id')->references('id')->on('environments')->onDelete('set null');
        });

        // Create chat search index table
        Schema::create('chat_search_index', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('message_id')->unique();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('environment_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('content'); // Full-text searchable content
            $table->timestamp('message_date');
            $table->boolean('is_archived')->default(false);
            $table->timestamp('indexed_at');
            $table->timestamps();

            // Full-text search index on content
            $table->fullText(['content'], 'chat_search_fulltext');

            // Regular indexes for filtering
            $table->index(['course_id', 'message_date'], 'search_course_date');
            $table->index(['environment_id', 'message_date'], 'search_env_date');
            $table->index(['user_id', 'message_date'], 'search_user_date');
            $table->index(['is_archived', 'message_date'], 'search_archived_date');
            $table->index(['course_id', 'is_archived'], 'search_course_archived');

            // Foreign key constraints
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('environment_id')->references('id')->on('environments')->onDelete('set null');
        });

        // Create search logs table for analytics
        Schema::create('search_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('environment_id')->nullable();
            $table->string('query', 255);
            $table->integer('result_count')->unsigned()->default(0);
            $table->decimal('response_time_ms', 8, 2)->nullable(); // Track search performance
            $table->string('user_agent', 500)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('searched_at');
            $table->timestamps();

            // Indexes for analytics queries
            $table->index(['course_id', 'searched_at'], 'search_logs_course_date');
            $table->index(['environment_id', 'searched_at'], 'search_logs_env_date');
            $table->index(['user_id', 'searched_at'], 'search_logs_user_date');
            $table->index(['query', 'searched_at'], 'search_logs_query_date');
            $table->index(['searched_at']);

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('set null');
            $table->foreign('environment_id')->references('id')->on('environments')->onDelete('set null');
        });

        // Create search performance metrics table
        Schema::create('search_performance_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('environment_id')->nullable();
            $table->date('metric_date');
            $table->integer('total_searches')->unsigned()->default(0);
            $table->integer('unique_users')->unsigned()->default(0);
            $table->decimal('avg_response_time_ms', 8, 2)->nullable();
            $table->decimal('avg_results_per_search', 6, 2)->nullable();
            $table->integer('zero_result_searches')->unsigned()->default(0);
            $table->json('top_queries')->nullable(); // Store top 10 queries as JSON
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['course_id', 'environment_id', 'metric_date'], 'search_metrics_unique');

            // Indexes
            $table->index(['course_id', 'metric_date'], 'search_metrics_course_date');
            $table->index(['environment_id', 'metric_date'], 'search_metrics_env_date');
            $table->index(['metric_date']);

            // Foreign key constraints
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('set null');
            $table->foreign('environment_id')->references('id')->on('environments')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('search_performance_metrics');
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('chat_search_index');
        Schema::dropIfExists('archival_jobs');
        Schema::dropIfExists('archived_chat_messages');
    }
};