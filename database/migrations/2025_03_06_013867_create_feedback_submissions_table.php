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
        if (MigrationHelper::tableExists('feedback_submissions')) {
            echo "Table 'feedback_submissions' already exists, skipping...\n";
        } else {
            Schema::create('feedback_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_content_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('submission_date')->useCurrent();
            $table->string('status')->default('submitted'); // draft, submitted, reviewed
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['feedback_content_id', 'user_id']);
            $table->index('status');
            $table->index('submission_date');
            
            // If multiple submissions are not allowed, we can add a unique constraint
            // But we'll leave it commented out since the FeedbackContent model has a flag for this
            // $table->unique(['feedback_content_id', 'user_id'], 'unique_feedback_submission');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_submissions');
    }
};
