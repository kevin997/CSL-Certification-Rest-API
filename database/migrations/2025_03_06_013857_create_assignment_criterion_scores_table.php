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
        if (MigrationHelper::tableExists('assignment_criterion_scores')) {
            echo "Table 'assignment_criterion_scores' already exists, skipping...\n";
        } else {
            Schema::create('assignment_criterion_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_submission_id')->constrained()->onDelete('cascade');
            $table->foreignId('assignment_criterion_id')->constrained()->onDelete('cascade');
            $table->integer('score');
            $table->text('feedback')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('assignment_submission_id');
            $table->index('assignment_criterion_id');
            
            // Unique constraint to prevent duplicate scores for the same criterion and submission
            $table->unique(['assignment_submission_id', 'assignment_criterion_id'], 'unique_criterion_score');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_criterion_scores');
    }
};
