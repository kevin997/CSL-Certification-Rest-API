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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('template_id')->constrained()->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users');
            $table->string('status')->default('draft'); // draft, published, archived
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('is_self_paced')->default(true);
            $table->integer('estimated_duration')->nullable(); // in minutes
            $table->string('difficulty_level')->nullable(); // beginner, intermediate, advanced
            $table->string('thumbnail_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('template_id');
            $table->index('created_by');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
            $table->index('difficulty_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
