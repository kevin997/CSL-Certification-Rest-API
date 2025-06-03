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
        // Skip creation if table already exists (from SQL dump)
        if (MigrationHelper::tableExists('lesson_discussions')) {
            echo "Table 'lesson_discussions' already exists, skipping...\n";
        } else {
            Schema::create('lesson_discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_content_id')->constrained()->onDelete('cascade');
            $table->foreignId('content_part_id')->nullable()->constrained('lesson_content_parts')->onDelete('set null');
            $table->foreignId('question_id')->nullable()->constrained('lesson_questions')->onDelete('set null');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('message');
            $table->foreignId('parent_id')->nullable()->constrained('lesson_discussions')->onDelete('cascade');
            $table->boolean('is_instructor_feedback')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('lesson_content_id');
            $table->index('content_part_id');
            $table->index('question_id');
            $table->index('user_id');
            $table->index('parent_id');
            $table->index('is_instructor_feedback');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_discussions');
    }
};
