<?php

use App\Helpers\MigrationHelper;
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
        // Create course_discussions table
        if (MigrationHelper::tableExists('course_discussions')) {
            echo "Table 'course_discussions' already exists, skipping...\n";
        } else {
            Schema::create('course_discussions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
                $table->foreignId('environment_id')->constrained('environments')->onDelete('cascade');
                $table->enum('type', ['group', 'private'])->default('group');
                $table->timestamps();
                $table->softDeletes();

                // Indexes for performance
                $table->index(['course_id', 'environment_id']);
                $table->index('type');
            });
        }

        // Create discussion_messages table
        if (MigrationHelper::tableExists('discussion_messages')) {
            echo "Table 'discussion_messages' already exists, skipping...\n";
        } else {
            Schema::create('discussion_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('discussion_id')->constrained('course_discussions')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->text('message_content');
                $table->enum('message_type', ['text', 'file', 'system'])->default('text');
                $table->foreignId('parent_message_id')->nullable()->constrained('discussion_messages')->onDelete('cascade');
                $table->timestamps();
                $table->softDeletes();

                // Indexes for performance
                $table->index(['discussion_id', 'created_at']);
                $table->index('user_id');
                $table->index('parent_message_id');
                $table->index('message_type');
            });
        }

        // Create discussion_participants table
        if (MigrationHelper::tableExists('discussion_participants')) {
            echo "Table 'discussion_participants' already exists, skipping...\n";
        } else {
            Schema::create('discussion_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('discussion_id')->constrained('course_discussions')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->timestamp('last_read_at')->nullable();
                $table->boolean('is_online')->default(false);
                $table->timestamps();

                // Unique constraint to prevent duplicate participants
                $table->unique(['discussion_id', 'user_id']);

                // Indexes for performance
                $table->index(['user_id', 'discussion_id']);
                $table->index('is_online');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discussion_participants');
        Schema::dropIfExists('discussion_messages');
        Schema::dropIfExists('course_discussions');
    }
};
