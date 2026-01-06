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
        if (!Schema::hasTable('webinar_contents')) {
            Schema::create('webinar_contents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('activity_id')->constrained()->onDelete('cascade');
                $table->foreignId('live_session_id')->nullable()->constrained()->onDelete('set null');
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->integer('duration_minutes')->default(60);
                $table->integer('max_participants')->default(100);
                $table->boolean('allow_recording')->default(false);
                $table->boolean('enable_chat')->default(true);
                $table->boolean('enable_qa')->default(true);
                $table->boolean('enable_reactions')->default(true);
                $table->boolean('mute_participants_on_join')->default(true);
                $table->boolean('disable_participant_video')->default(false);
                $table->string('access_type')->default('enrolled'); // enrolled, public, invited
                $table->json('settings')->nullable();
                $table->json('hosts')->nullable(); // Array of host user IDs
                $table->json('co_hosts')->nullable(); // Array of co-host user IDs
                $table->text('join_instructions')->nullable();
                $table->text('prerequisites')->nullable();
                $table->string('recording_url')->nullable();
                $table->string('status')->default('draft'); // draft, scheduled, live, completed, cancelled
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['activity_id']);
                $table->index(['live_session_id']);
                $table->index(['status']);
                $table->index(['scheduled_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webinar_contents');
    }
};
