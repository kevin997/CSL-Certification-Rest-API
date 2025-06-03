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
        if (MigrationHelper::tableExists('activities')) {
            echo "Table 'activities' already exists, skipping...\n";
        } else {
            Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // text, video, quiz, assessment, lesson, document, assignment
            $table->integer('order')->default(0);
            $table->foreignId('block_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('active'); // active, inactive
            $table->foreignId('created_by')->constrained('users');
            $table->string('content_type')->nullable(); // For polymorphic relationship
            $table->unsignedBigInteger('content_id')->nullable(); // For polymorphic relationship
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for faster queries and ordering
            $table->index(['block_id', 'order']);
            $table->index('status');
            $table->index('type');
            $table->index(['content_type', 'content_id']); // Index for polymorphic relationship
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
