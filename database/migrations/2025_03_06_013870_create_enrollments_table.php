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
        if (MigrationHelper::tableExists('enrollments')) {
            echo "Table 'enrollments' already exists, skipping...\n";
        } else {
            Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('enrolled'); // enrolled, in-progress, completed, dropped
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->float('progress_percentage')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['course_id', 'user_id']);
            $table->index('status');
            $table->index('enrolled_at');
            $table->index('completed_at');
            
            // Unique constraint to prevent duplicate enrollments
            $table->unique(['course_id', 'user_id'], 'unique_enrollment');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
