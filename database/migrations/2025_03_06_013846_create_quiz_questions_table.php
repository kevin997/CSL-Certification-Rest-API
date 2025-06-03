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
        if (MigrationHelper::tableExists('quiz_questions')) {
            echo "Table 'quiz_questions' already exists, skipping...\n";
        } else {
            Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_content_id')->constrained()->onDelete('cascade');
            $table->text('question');
            $table->string('question_type'); // multiple_choice, true_false, short_answer, etc.
            $table->integer('points')->default(1);
            $table->integer('order')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['quiz_content_id', 'order']);
            $table->index('question_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
