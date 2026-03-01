<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('video_hand_in_contents')) {
            Schema::create('video_hand_in_contents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('activity_id')->constrained()->onDelete('cascade');
                $table->string('title');
                $table->text('description')->nullable();
                $table->longText('instructions');
                $table->string('instructions_format')->default('markdown');
                $table->integer('max_duration')->nullable(); // seconds
                $table->json('allowed_formats')->nullable(); // e.g. ["mp4","mov"]
                $table->integer('max_file_size')->nullable(); // MB
                $table->timestamp('due_date')->nullable();
                $table->boolean('allow_late_submissions')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('video_hand_in_contents');
    }
};
