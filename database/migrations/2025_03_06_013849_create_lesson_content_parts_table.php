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
        if (MigrationHelper::tableExists('lesson_content_parts')) {
            echo "Table 'lesson_content_parts' already exists, skipping...\n";
        } else {
            Schema::create('lesson_content_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_content_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('content_type'); // wysiwyg, video
            $table->longText('content')->nullable(); // For WYSIWYG content
            $table->string('video_url')->nullable(); // For video content
            $table->string('video_provider')->nullable(); // youtube, vimeo, etc.
            $table->integer('order')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['lesson_content_id', 'order']);
            $table->index('content_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_content_parts');
    }
};
